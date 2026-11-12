<?php

/**
 * Avisos. Não têm tela própria: aparecem na janela do sininho (layout/_avisos.php).
 * A rota antiga "notificacoes" só abre essa janela no Início, para links e favoritos antigos.
 */
final class NotificacoesController extends Controller
{
    public function lista(): void
    {
        header('Location: ./inicio#avisos');
        exit;
    }

    /** Marca como lida e abre o link do aviso. */
    public function abrir(): void
    {
        $link = (new Notificacao())->ler($this->idDaUrl()) ?? './inicio';
        header('Location: ' . self::interno($link, './inicio'));
        exit;
    }

    /** Marca todos como lidos. Pela janela (fetch) responde só "ok"; sem JS, volta à página de onde veio. */
    public function lerTodas(): void
    {
        (new Notificacao())->lerTodas();
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            $this->json(['ok' => true]);
        }
        $voltar = (string) ($_POST['voltar'] ?? '');
        $rota = preg_match('#/([a-z0-9\-]+(\?[^\s\#]*)?)$#i', parse_url($voltar, PHP_URL_PATH) . (($q = parse_url($voltar, PHP_URL_QUERY)) ? '?' . $q : ''), $m) ? './' . $m[1] : './inicio';
        header('Location: ' . self::interno($rota, './inicio') . '#avisos');
        exit;
    }

    /** Só links internos relativos (./rota?...): nada de redirecionar para fora. */
    private static function interno(string $link, string $padrao): string
    {
        return preg_match('#^\./[a-z0-9\-]+(\?[^\s]*)?$#i', $link) ? $link : $padrao;
    }
}
