<?php

require_once __DIR__ . '/../core/Transacao.php';
require_once __DIR__ . '/../core/Integracoes.php';
require_once __DIR__ . '/../core/WhatsAppCloud.php';
require_once __DIR__ . '/Conversa.php';
require_once __DIR__ . '/Consentimento.php';
require_once __DIR__ . '/MensagensCliente.php';
require_once __DIR__ . '/PortalToken.php';
require_once __DIR__ . '/Notificacao.php';

/**
 * Comunicação com o cliente: a fila de mensagens, o envio pelos canais reais e a recepção.
 *
 * MUDANÇA DE ESTADO → MENSAGEM. Os models chamam `Comunicacao::evento()` DENTRO da transação que
 * muda o estado: a mensagem nasce na MESMA transação (se a mudança desfaz, a mensagem some), com a
 * chave do fato — repetir o mesmo fato nunca gera uma segunda mensagem. O envio em si (rede) NUNCA
 * acontece dentro da transação: a mensagem entra na fila (PENDENTE) e `despacharPendentes()` a envia
 * depois, com nova tentativa se a rede falhar.
 *
 * NADA É FINGIDO. O resultado de cada mensagem fica visível na conversa:
 *   PENDENTE · ENVIADA · ENTREGUE · LIDA (recibos do WhatsApp) · FALHA (com o erro)
 *   NAO_CONFIGURADO (canal sem credencial) · SEM_CONSENTIMENTO · SEM_CONTATO · REGISTRADA (manual)
 *
 * Canal: WhatsApp se está configurado e o cliente consentiu; senão e-mail, se está configurado e o
 * cliente não pediu para parar. Um canal só por mensagem (nunca as duas, para não duplicar).
 * WhatsApp: texto livre só dentro da janela de 24 h da última mensagem do cliente; fora dela, só
 * template aprovado — sem template configurado, a mensagem fica NAO_CONFIGURADO com o motivo.
 */
final class Comunicacao
{
    public const MAX_TENTATIVAS = 3;

    private PDO $conn;
    private WhatsAppCloud $wa;
    /** @var callable */
    private $enviarEmail;
    private bool $emailConfigurado;

    public function __construct(?PDO $conn = null, ?WhatsAppCloud $wa = null, ?callable $enviarEmail = null, ?bool $emailConfigurado = null)
    {
        $this->conn = $conn ?? new Database();
        $this->wa = $wa ?? new WhatsAppCloud();
        $this->emailConfigurado = $emailConfigurado ?? Integracoes::emailConfigurado();
        // static: o closure não pode capturar $this (seria um ciclo Comunicacao↔closure que segura a conexão até o GC de ciclos).
        $this->enviarEmail = $enviarEmail ?? static function (string $para, string $assunto, string $html, string $texto): void {
            require_once __DIR__ . '/../core/Mailer.php';
            (new Mailer())->enviar($para, $assunto, $html, $texto);
        };
    }

    /**
     * Atalho para os models: enfileira a mensagem do evento, na transação de quem chama.
     *
     * @param array<string,mixed>      $dados  campos do texto (placa, veiculo, numero, valor, saldo…)
     * @param array{escopo:string, entidade:int}|null $portal gera o link seguro do portal para a mensagem
     */
    public static function evento(PDO $pdo, string $evento, int $clienteId, array $dados, string $chaveFato, ?string $contextoTipo = null, ?int $contextoId = null, ?array $portal = null): ?int
    {
        try {
            return (new self($pdo))->registrarEvento($evento, $clienteId, $dados, $chaveFato, $contextoTipo, $contextoId, $portal);
        } catch (\Throwable $e) {
            // A mensagem ao cliente nunca derruba a operação que a originou (aprovar, conferir, pagar…).
            error_log('[Comunicacao evento ' . $evento . '] ' . $e->getMessage());
            return null;
        }
    }

    /** @return array{placa:string, veiculo:string} para o texto das mensagens */
    public static function dadosDoVeiculo(PDO $pdo, ?int $veiculoId): array
    {
        if (!$veiculoId) {
            return ['placa' => '', 'veiculo' => ''];
        }

        $stmt = $pdo->prepare("SELECT v.placa, CONCAT(mv.nome, ' ', mo.nome) AS veiculo FROM veiculos v JOIN marcas_veiculos mv ON mv.id_marca = v.marca_id JOIN modelos_veiculos mo ON mo.id_modelo = v.modelo_id WHERE v.id_veiculo = ?");
        $stmt->execute([$veiculoId]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['placa' => '', 'veiculo' => ''];

        // O veículo do atendimento rápido ainda pode estar sem marca/modelo ('Não informado').
        return ['placa' => (string) $v['placa'], 'veiculo' => str_contains((string) $v['veiculo'], 'Não informado') ? '' : (string) $v['veiculo']];
    }

    /* ── Enfileirar ──────────────────────────────────────────────────────── */

    public function registrarEvento(string $evento, int $clienteId, array $dados, string $chaveFato, ?string $contextoTipo = null, ?int $contextoId = null, ?array $portal = null): ?int
    {
        $chave = 'EV:' . $evento . ':' . $chaveFato;

        $ja = $this->conn->prepare('SELECT id_mensagem FROM mensagens WHERE chave_idempotencia = ?');
        $ja->execute([$chave]);

        if ($id = $ja->fetchColumn()) {
            return (int) $id;
        }

        $cli = $this->conn->prepare('SELECT id_cliente, nome, telefone, email FROM clientes WHERE id_cliente = ?');
        $cli->execute([$clienteId]);
        $cliente = $cli->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            return null;
        }

        [$canal, $status, $destino, $erro] = $this->decidirCanal($cliente);

        $link = '';
        if ($portal !== null) {
            $link = PortalToken::url(PortalToken::emitir($this->conn, $clienteId, $portal['escopo'], (int) $portal['entidade']));
        }

        $empresa = function_exists('env') ? (string) env('EMPRESA_NOME', 'Mecanize') : 'Mecanize';
        $msg = MensagensCliente::montar($evento, $dados + ['nome' => $cliente['nome'], 'link' => $link, 'empresa' => $empresa ?: 'Mecanize']);

        $conversaId = $this->conversaDoCliente($cliente, $canal);

        try {
            $this->conn->prepare("
                INSERT INTO mensagens (conversa_id, direcao, tipo, evento, canal, destino, corpo, status, erro, contexto_tipo, contexto_id, chave_idempotencia, parametros_template)
                VALUES (?, 'SAIDA', 'AUTOMATICA', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $conversaId, $evento, $canal, $destino, $msg['texto'], $status, $erro, $contextoTipo, $contextoId, $chave,
                json_encode($msg['parametros'], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'uq_mensagem_chave')) {
                $ja->execute([$chave]);
                return ($id = $ja->fetchColumn()) ? (int) $id : null;
            }

            throw $e;
        }

        $id = (int) $this->conn->lastInsertId();
        $this->conn->prepare('UPDATE conversas SET ultima_mensagem_em = NOW() WHERE id_conversa = ?')->execute([$conversaId]);

        return $id;
    }

    /**
     * Qual canal usar e, se nenhum serve, por quê.
     *
     * @return array{0:string,1:string,2:?string,3:?string} canal, status inicial, destino, motivo
     */
    private function decidirCanal(array $cliente): array
    {
        $tel = WhatsAppCloud::normalizarTelefone($cliente['telefone'] ?? null);
        $email = filter_var($cliente['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $cliente['email'] : null;
        $id = (int) $cliente['id_cliente'];

        $waOk = $this->wa->configurado();
        $waConsente = $tel !== null && Consentimento::permite($this->conn, $id, 'WHATSAPP');
        $emailConsente = $email !== null && Consentimento::permite($this->conn, $id, 'EMAIL');

        if ($waOk && $waConsente) {
            return ['WHATSAPP', 'PENDENTE', $tel, null];
        }

        if ($this->emailConfigurado && $emailConsente) {
            return ['EMAIL', 'PENDENTE', $email, null];
        }

        $preferido = $tel !== null ? 'WHATSAPP' : ($email !== null ? 'EMAIL' : 'MANUAL');
        $destino = $tel ?? $email;

        if ($tel === null && $email === null) {
            return ['MANUAL', 'SEM_CONTATO', null, 'O cliente não tem telefone nem e-mail válidos no cadastro.'];
        }

        if (!$waOk && !$this->emailConfigurado) {
            return [$preferido, 'NAO_CONFIGURADO', $destino, 'Nenhum canal de envio está configurado (WhatsApp ou e-mail). Veja Integrações.'];
        }

        if ($tel !== null && $waOk && !$waConsente) {
            return ['WHATSAPP', 'SEM_CONSENTIMENTO', $tel, 'O cliente não tem consentimento registrado para receber mensagens por WhatsApp.'];
        }

        if ($email !== null && $this->emailConfigurado && !$emailConsente) {
            return ['EMAIL', 'SEM_CONSENTIMENTO', $email, 'O cliente pediu para não receber e-mails.'];
        }

        return [$preferido, 'NAO_CONFIGURADO', $destino, 'O canal que este cliente aceita não está configurado. Veja Integrações.'];
    }

    /** A conversa aberta do cliente (qualquer canal); se não houver, abre uma no canal preferido. */
    private function conversaDoCliente(array $cliente, string $canal): int
    {
        $aberta = $this->conn->prepare("SELECT id_conversa FROM conversas WHERE cliente_id = ? AND status <> 'ENCERRADA' ORDER BY ultima_mensagem_em DESC, id_conversa DESC LIMIT 1");
        $aberta->execute([$cliente['id_cliente']]);

        if ($id = $aberta->fetchColumn()) {
            return (int) $id;
        }

        $identificador = $canal === 'WHATSAPP'
            ? WhatsAppCloud::normalizarTelefone($cliente['telefone'] ?? null)
            : ($canal === 'EMAIL' ? ($cliente['email'] ?? null) : null);

        return Conversa::abertaOuNova($this->conn, (int) $cliente['id_cliente'], $canal, $identificador, $cliente['nome'], 'Acompanhamento do atendimento');
    }

    /* ── Resposta e registros manuais da equipe ──────────────────────────── */

    /**
     * Responde na conversa pelo canal dela. WhatsApp: texto livre só dentro da janela de 24 h.
     *
     * @return array{ok:bool, erro:?string, id:?int, status:?string}
     */
    public function responder(int $conversaId, string $texto, ?int $usuarioId): array
    {
        $texto = trim($texto);

        if ($texto === '') {
            return ['ok' => false, 'erro' => 'Escreva a mensagem.', 'id' => null, 'status' => null];
        }

        $c = $this->conn->prepare('SELECT * FROM conversas WHERE id_conversa = ?');
        $c->execute([$conversaId]);
        $conversa = $c->fetch(PDO::FETCH_ASSOC);

        if (!$conversa) {
            return ['ok' => false, 'erro' => 'Conversa não encontrada.', 'id' => null, 'status' => null];
        }

        if ($conversa['status'] === 'ENCERRADA') {
            return ['ok' => false, 'erro' => 'A conversa está encerrada. Reabra para responder.', 'id' => null, 'status' => null];
        }

        if ($conversa['canal'] === 'MANUAL') {
            return ['ok' => false, 'erro' => 'Esta conversa não tem canal de envio. Use "registrar contato" para anotar o que foi dito por fora do sistema.', 'id' => null, 'status' => null];
        }

        if (empty($conversa['identificador'])) {
            return ['ok' => false, 'erro' => 'A conversa não tem destino (telefone/e-mail).', 'id' => null, 'status' => null];
        }

        if ($conversa['canal'] === 'WHATSAPP') {
            if (!$this->wa->configurado()) {
                return ['ok' => false, 'erro' => 'O WhatsApp não está configurado: não dá para enviar daqui. Veja Integrações.', 'id' => null, 'status' => null];
            }

            if (!WhatsAppCloud::dentroDaJanela($conversa['ultima_entrada_em'])) {
                return ['ok' => false, 'erro' => 'Passaram mais de 24 h desde a última mensagem do cliente: o WhatsApp só permite template aprovado. Registre o contato ou envie por outro canal.', 'id' => null, 'status' => null];
            }
        } elseif (!$this->emailConfigurado) {
            return ['ok' => false, 'erro' => 'O e-mail não está configurado. Veja Integrações.', 'id' => null, 'status' => null];
        }

        $this->conn->prepare("
            INSERT INTO mensagens (conversa_id, direcao, tipo, canal, destino, corpo, status, autor_id)
            VALUES (?, 'SAIDA', 'TEXTO', ?, ?, ?, 'PENDENTE', ?)
        ")->execute([$conversaId, $conversa['canal'], $conversa['identificador'], mb_substr($texto, 0, 4000), $usuarioId ?: null]);
        $id = (int) $this->conn->lastInsertId();

        $this->conn->prepare("UPDATE conversas SET ultima_mensagem_em = NOW(), status = IF(status = 'ABERTA', 'AGUARDANDO_CLIENTE', status), nao_lidas = 0 WHERE id_conversa = ?")->execute([$conversaId]);

        $this->enviarMensagem($id);

        $status = $this->conn->query("SELECT status FROM mensagens WHERE id_mensagem = {$id}")->fetchColumn();

        return ['ok' => true, 'erro' => null, 'id' => $id, 'status' => (string) $status];
    }

    /** O que foi dito por fora do sistema (ligação, balcão, WhatsApp pessoal) — marcado como registro manual. */
    public function registrarManual(int $conversaId, string $direcao, string $texto, ?int $usuarioId): array
    {
        $texto = trim($texto);

        if (!in_array($direcao, ['ENTRADA', 'SAIDA', 'NOTA'], true) || $texto === '') {
            return ['ok' => false, 'erro' => 'Informe o que foi dito.', 'id' => null];
        }

        $existe = $this->conn->prepare('SELECT 1 FROM conversas WHERE id_conversa = ?');
        $existe->execute([$conversaId]);

        if (!$existe->fetchColumn()) {
            return ['ok' => false, 'erro' => 'Conversa não encontrada.', 'id' => null];
        }

        $this->conn->prepare("INSERT INTO mensagens (conversa_id, direcao, tipo, canal, corpo, status, autor_id) VALUES (?, ?, 'TEXTO', 'MANUAL', ?, 'REGISTRADA', ?)")
            ->execute([$conversaId, $direcao, mb_substr($texto, 0, 4000), $usuarioId ?: null]);
        $id = (int) $this->conn->lastInsertId();

        $this->conn->prepare('UPDATE conversas SET ultima_mensagem_em = NOW() WHERE id_conversa = ?')->execute([$conversaId]);

        return ['ok' => true, 'erro' => null, 'id' => $id];
    }

    /* ── Envio ───────────────────────────────────────────────────────────── */

    /** Envia as mensagens PENDENTES (fila). @return array{enviadas:int, falhas:int, puladas:int} */
    public function despacharPendentes(int $limite = 10): array
    {
        $ids = $this->conn->query("SELECT id_mensagem FROM mensagens WHERE status = 'PENDENTE' AND tentativas < " . self::MAX_TENTATIVAS . ' ORDER BY id_mensagem LIMIT ' . (int) $limite)->fetchAll(PDO::FETCH_COLUMN);
        $r = ['enviadas' => 0, 'falhas' => 0, 'puladas' => 0];

        foreach ($ids as $id) {
            $status = $this->enviarMensagem((int) $id);

            if ($status === null) {
                $r['puladas']++;
            } elseif ($status === 'ENVIADA') {
                $r['enviadas']++;
            } elseif (in_array($status, ['FALHA', 'NAO_CONFIGURADO'], true)) {
                $r['falhas']++;
            }
        }

        return $r;
    }

    public function haPendentes(): bool
    {
        return (bool) $this->conn->query("SELECT 1 FROM mensagens WHERE status = 'PENDENTE' AND tentativas < " . self::MAX_TENTATIVAS . ' LIMIT 1')->fetchColumn();
    }

    /**
     * Envia UMA mensagem. A "reserva" é um compare-and-swap em `tentativas`: dois processos
     * (o despacho do fim da requisição e o agendador) não enviam a mesma mensagem.
     *
     * @return string|null o status final, ou null se outro processo já estava com ela
     */
    public function enviarMensagem(int $id): ?string
    {
        $stmt = $this->conn->prepare("SELECT m.*, c.ultima_entrada_em FROM mensagens m JOIN conversas c ON c.id_conversa = m.conversa_id WHERE m.id_mensagem = ? AND m.status = 'PENDENTE'");
        $stmt->execute([$id]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$m) {
            return null;
        }

        $reserva = $this->conn->prepare("UPDATE mensagens SET tentativas = tentativas + 1 WHERE id_mensagem = ? AND status = 'PENDENTE' AND tentativas = ?");
        $reserva->execute([$id, $m['tentativas']]);

        if ($reserva->rowCount() !== 1) {
            return null;
        }

        $tentativa = (int) $m['tentativas'] + 1;

        try {
            [$status, $providerId, $erro, $retentavel] = $m['canal'] === 'WHATSAPP' ? $this->enviarWhatsApp($m) : $this->enviarEmailMsg($m);
        } catch (\Throwable $e) {
            [$status, $providerId, $erro, $retentavel] = ['FALHA', null, mb_substr($e->getMessage(), 0, 250), true];
        }

        if ($status === 'FALHA' && $retentavel && $tentativa < self::MAX_TENTATIVAS) {
            $status = 'PENDENTE'; // tenta de novo no próximo despacho
        }

        try {
            $this->conn->prepare("
                UPDATE mensagens SET status = ?, erro = ?, provider_message_id = COALESCE(?, provider_message_id),
                       enviada_em = IF(? = 'ENVIADA', NOW(), enviada_em)
                WHERE id_mensagem = ?
            ")->execute([$status, $erro, $providerId, $status, $id]);
        } catch (PDOException $e) {
            error_log('[Comunicacao enviarMensagem] ' . $e->getMessage());
        }

        return $status;
    }

    /** @return array{0:string,1:?string,2:?string,3:bool} status, id no provedor, erro, retentável */
    private function enviarWhatsApp(array $m): array
    {
        if (!$this->wa->configurado()) {
            return ['NAO_CONFIGURADO', null, 'WhatsApp não configurado.', false];
        }

        if (WhatsAppCloud::dentroDaJanela($m['ultima_entrada_em']) && $m['tipo'] !== 'TEMPLATE') {
            $r = $this->wa->enviarTexto((string) $m['destino'], (string) $m['corpo']);
        } else {
            $template = $m['evento'] ? $this->wa->templateDoEvento((string) $m['evento']) : null;

            if ($template === null) {
                return ['NAO_CONFIGURADO', null, 'Fora da janela de 24 h do WhatsApp e sem template aprovado configurado para este tipo de mensagem.', false];
            }

            $r = $this->wa->enviarTemplate((string) $m['destino'], $template, (array) json_decode((string) ($m['parametros_template'] ?? '[]'), true));
        }

        return $r['ok'] ? ['ENVIADA', $r['id'], null, false] : ['FALHA', null, $r['erro'], $r['retentavel']];
    }

    private function enviarEmailMsg(array $m): array
    {
        if (!$this->emailConfigurado) {
            return ['NAO_CONFIGURADO', null, 'E-mail não configurado.', false];
        }

        $empresa = function_exists('env') ? (string) env('EMPRESA_NOME', 'Mecanize') : 'Mecanize';
        $assunto = $m['evento'] ? (MensagensCliente::EVENTOS[$m['evento']] ?? 'Atualização') . ' — ' . $empresa : 'Mensagem da ' . $empresa;
        $texto = (string) $m['corpo'];

        // O texto já é só nosso (sem HTML do cliente), mas vai escapado e os links viram clicáveis.
        $html = preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', nl2br(htmlspecialchars($texto, ENT_QUOTES)));

        ($this->enviarEmail)((string) $m['destino'], $assunto, '<p>' . $html . '</p>', $texto);

        return ['ENVIADA', null, null, false];
    }

    /* ── Recepção (webhook) ──────────────────────────────────────────────── */

    /**
     * Registra a mensagem que o cliente mandou. Idempotente pelo id da mensagem no provedor.
     *
     * @return array{ok:bool, duplicada:bool, conversa_id:?int, cliente_id:?int}
     */
    public function receber(string $canal, string $identificador, ?string $nome, string $texto, ?string $providerId, ?int $em = null): array
    {
        return Transacao::executar($this->conn, function () use ($canal, $identificador, $nome, $texto, $providerId, $em) {
            if ($providerId !== null) {
                $ja = $this->conn->prepare('SELECT conversa_id FROM mensagens WHERE provider_message_id = ?');
                $ja->execute([$providerId]);

                if ($cid = $ja->fetchColumn()) {
                    return ['ok' => true, 'duplicada' => true, 'conversa_id' => (int) $cid, 'cliente_id' => null];
                }
            }

            $clienteId = $canal === 'WHATSAPP' ? Conversa::clientePorTelefone($this->conn, $identificador) : null;

            if ($clienteId === null && $canal === 'EMAIL') {
                $c = $this->conn->prepare('SELECT id_cliente FROM clientes WHERE ativo = 1 AND LOWER(email) = LOWER(?) LIMIT 1');
                $c->execute([$identificador]);
                $clienteId = ($x = $c->fetchColumn()) ? (int) $x : null;
            }

            $conversaId = Conversa::abertaOuNova($this->conn, $clienteId, $canal, $identificador, $nome, $clienteId ? 'Mensagem do cliente' : 'Contato não identificado');
            $quando = date('Y-m-d H:i:s', $em ?: time());

            $this->conn->prepare("INSERT INTO mensagens (conversa_id, direcao, tipo, canal, destino, corpo, status, provider_message_id, criada_em) VALUES (?, 'ENTRADA', 'TEXTO', ?, ?, ?, 'RECEBIDA', ?, ?)")
                ->execute([$conversaId, $canal, $identificador, mb_substr($texto, 0, 4000), $providerId, $quando]);

            $this->conn->prepare("UPDATE conversas SET ultima_mensagem_em = ?, ultima_entrada_em = ?, nao_lidas = nao_lidas + 1, status = IF(status = 'AGUARDANDO_CLIENTE', 'ABERTA', status) WHERE id_conversa = ?")
                ->execute([$quando, $quando, $conversaId]);

            $this->avisarEquipe($conversaId, $clienteId, $nome ?: $identificador, $texto, $providerId ?? uniqid('msg', true));

            // "PARAR" é um pedido de descadastro (LGPD): vira decisão registrada, sem esperar a equipe ler.
            if ($clienteId !== null && $canal === 'WHATSAPP' && self::pedidoParaParar($texto)) {
                Consentimento::registrar($this->conn, $clienteId, 'WHATSAPP', false, 'WHATSAPP', null, 'Pediu para parar de receber mensagens, pelo próprio WhatsApp');
            }

            return ['ok' => true, 'duplicada' => false, 'conversa_id' => $conversaId, 'cliente_id' => $clienteId];
        });
    }

    /** A mensagem inteira é uma palavra de descadastro? ("quero parar" no meio de uma frase NÃO é, de propósito) */
    public static function pedidoParaParar(string $texto): bool
    {
        $t = mb_strtoupper(trim($texto));
        $t = strtr($t, ['Ã' => 'A', 'Â' => 'A', 'Á' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Ú' => 'U', 'Ç' => 'C']);
        $t = rtrim($t, '.! ');

        return in_array($t, ['PARAR', 'SAIR', 'STOP', 'CANCELAR', 'DESCADASTRAR', 'NAO QUERO RECEBER', 'NAO QUERO MAIS RECEBER'], true);
    }

    /**
     * Recebe o webhook do WhatsApp (POST). A assinatura é conferida sobre o corpo BRUTO antes de qualquer
     * leitura: sem assinatura válida nada é lido nem gravado. Idempotente (o mesmo evento reenviado pela
     * Meta não duplica mensagem nem retrocede recibo).
     *
     * @return array{http:int, motivo:string, mensagens:int, recibos:int}
     */
    public function receberWebhookWhatsApp(string $corpoBruto, ?string $assinatura): array
    {
        $zero = ['mensagens' => 0, 'recibos' => 0];

        if (strlen($corpoBruto) > 1048576) {
            return ['http' => 413, 'motivo' => 'corpo_grande'] + $zero;
        }

        if (!$this->wa->validarAssinatura($corpoBruto, $assinatura)) {
            error_log('[webhook WhatsApp] Assinatura ausente ou inválida — descartado.');
            return ['http' => 401, 'motivo' => 'assinatura_invalida'] + $zero;
        }

        $payload = json_decode($corpoBruto, true);

        if (!is_array($payload)) {
            return ['http' => 400, 'motivo' => 'json_invalido'] + $zero;
        }

        try {
            $dados = $this->wa->interpretar($payload);
            $novas = 0;
            $recibos = 0;

            foreach ($dados['mensagens'] as $m) {
                if (!$this->receber('WHATSAPP', $m['de'], $m['nome'], $m['texto'], $m['id'], $m['em'])['duplicada']) {
                    $novas++;
                }
            }

            foreach ($dados['status'] as $st) {
                if ($this->atualizarEntrega($st['id'], $st['status'], $st['erro'])) {
                    $recibos++;
                }
            }

            return ['http' => 200, 'motivo' => 'ok', 'mensagens' => $novas, 'recibos' => $recibos];
        } catch (\Throwable $e) {
            // 500 faz a Meta tentar de novo; nada foi gravado pela metade (cada item tem a sua transação).
            error_log('[webhook WhatsApp] ' . $e->getMessage());
            return ['http' => 500, 'motivo' => 'erro_interno'] + $zero;
        }
    }

    private function avisarEquipe(int $conversaId, ?int $clienteId, string $de, string $texto, string $chave): void
    {
        $resp = $this->conn->prepare('SELECT responsavel_id FROM conversas WHERE id_conversa = ?');
        $resp->execute([$conversaId]);
        $responsavel = $resp->fetchColumn();

        $destinos = $responsavel
            ? [(int) $responsavel]
            : array_map('intval', $this->conn->query("SELECT id_usuario FROM usuarios WHERE ativo = 1 AND tipo IN ('ADMIN','ATENDENTE')")->fetchAll(PDO::FETCH_COLUMN));

        $nome = $clienteId ? ($this->conn->query('SELECT nome FROM clientes WHERE id_cliente = ' . (int) $clienteId)->fetchColumn() ?: $de) : $de . ' (não identificado)';

        Notificacao::paraUsuarios($this->conn, $destinos, 'CONVERSA', 'Nova mensagem de ' . $nome, mb_substr($texto, 0, 140), './viewConversa?id=' . $conversaId, 'MSG:' . $chave);
    }

    /**
     * Recibo de entrega do provedor. Nunca regride (LIDA não volta a ENTREGUE) e a falha só vale
     * para o que ainda não foi entregue.
     */
    public function atualizarEntrega(string $providerId, string $statusProvedor, ?string $erro = null): bool
    {
        $alvo = ['sent' => 'ENVIADA', 'delivered' => 'ENTREGUE', 'read' => 'LIDA', 'failed' => 'FALHA'][$statusProvedor] ?? null;

        if ($alvo === null) {
            return false;
        }

        $stmt = $this->conn->prepare('SELECT id_mensagem, status FROM mensagens WHERE provider_message_id = ? FOR UPDATE');
        $this->conn->beginTransaction();

        try {
            $stmt->execute([$providerId]);
            $m = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$m) {
                $this->conn->commit();
                return false;
            }

            $ordem = ['PENDENTE' => 0, 'ENVIADA' => 1, 'ENTREGUE' => 2, 'LIDA' => 3];
            $atual = $ordem[$m['status']] ?? 1;

            $aplicar = $alvo === 'FALHA' ? $atual < 2 : (($ordem[$alvo] ?? 0) > $atual);

            if ($aplicar) {
                $this->conn->prepare("
                    UPDATE mensagens SET status = ?, erro = ?,
                           entregue_em = IF(? IN ('ENTREGUE','LIDA') AND entregue_em IS NULL, NOW(), entregue_em),
                           lida_em = IF(? = 'LIDA', NOW(), lida_em)
                    WHERE id_mensagem = ?
                ")->execute([$alvo, $alvo === 'FALHA' ? mb_substr((string) $erro ?: 'O WhatsApp informou falha na entrega.', 0, 250) : null, $alvo, $alvo, $m['id_mensagem']]);
            }

            $this->conn->commit();

            return $aplicar;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }
}
