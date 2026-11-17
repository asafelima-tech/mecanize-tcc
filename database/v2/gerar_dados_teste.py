#!/usr/bin/env python3
"""Gera o SQL de dados de teste do Mecanize v2 (oficina 1). Determinístico (seed fixa).
Uso: python3 gerar.py [COMMIT|ROLLBACK] > seed.sql
"""
import random, sys, json, hashlib
from datetime import datetime, date, timedelta, time
from decimal import Decimal, ROUND_HALF_UP

FIM = sys.argv[1] if len(sys.argv) > 1 else 'ROLLBACK'
R = random.Random(20261117)
OF = 1
HOJE = date(2026, 11, 17)
AGORA = datetime(2026, 11, 17, 11, 30)
INICIO = HOJE - timedelta(days=185)

ADMINS = [5, 8, 6, 13]
ATEND = [3, 46]
TECS = [9, 10, 12]
BALCAO = ADMINS[:2] + ATEND

def D(x): return Decimal(str(x)).quantize(Decimal('0.01'), ROUND_HALF_UP)
def q(v):
    if v is None: return 'NULL'
    if isinstance(v, bool): return '1' if v else '0'
    if isinstance(v, (int, Decimal)): return str(v)
    if isinstance(v, float): return str(D(v))
    if isinstance(v, datetime): return "'" + v.strftime('%Y-%m-%d %H:%M:%S') + "'"
    if isinstance(v, date): return "'" + v.isoformat() + "'"
    return "'" + str(v).replace('\\', '\\\\').replace("'", "''") + "'"

linhas = []
def ins(tabela, d):
    linhas.append((tabela, d))

def dia_util(d):
    while d.weekday() == 6: d += timedelta(days=1)
    return d
def hora(d, h0=8, h1=17):
    return datetime.combine(d, time(R.randint(h0, h1), R.choice([0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55])))
def mais_horas(dt, h0, h1):
    novo = dt + timedelta(hours=R.uniform(h0, h1))
    if novo.hour >= 18: novo = datetime.combine(novo.date() + timedelta(days=1), time(8, R.randint(0, 50)))
    if novo.hour < 8: novo = novo.replace(hour=8)
    if novo.weekday() == 6: novo += timedelta(days=1)
    return novo.replace(second=0, microsecond=0)

# ── Clientes ────────────────────────────────────────────────────────────────
def cpf():
    n = [R.randint(0, 9) for _ in range(9)]
    for k in (10, 11):
        s = sum(a * b for a, b in zip(n, range(k, 1, -1))); dv = (s * 10) % 11
        n.append(0 if dv == 10 else dv)
    return ''.join(map(str, n))
def cnpj():
    n = [R.randint(0, 9) for _ in range(8)] + [0, 0, 0, 1]
    for pesos in ([5,4,3,2,9,8,7,6,5,4,3,2], [6,5,4,3,2,9,8,7,6,5,4,3,2]):
        s = sum(a * b for a, b in zip(n, pesos)); r = s % 11
        n.append(0 if r < 2 else 11 - r)
    return ''.join(map(str, n))

PF = ['Carlos Eduardo Silva', 'Mariana Oliveira Santos', 'João Pedro Almeida', 'Fernanda Costa Lima', 'Ricardo Pereira Souza',
      'Juliana Rodrigues', 'Marcos Antônio Ferreira', 'Patrícia Gomes', 'Lucas Martins Ribeiro', 'Aline Carvalho',
      'Roberto Nascimento', 'Camila Araújo', 'Thiago Barbosa', 'Beatriz Mendes', 'André Luiz Rocha', 'Gabriela Dias',
      'Paulo Henrique Moreira', 'Larissa Teixeira', 'Diego Cardoso', 'Vanessa Correia']
PJ = [('Transportadora Rota Oeste Ltda', 'rotaoeste'), ('Distribuidora Bom Preço Ltda', 'bompreco'), ('Construtora Alicerce Ltda', 'alicerce'),
      ('Agropecuária Santa Rita', 'santarita'), ('Farmácia Vida Plena', 'vidaplena')]
CIDADES = [('Presidente Prudente', 'SP', '19010', ['Centro', 'Vila Nova', 'Jardim Bongiovani', 'Parque Furquim', 'Jardim Paulista']),
           ('Santo Anastácio', 'SP', '19360', ['Centro', 'Vila Esperança', 'Jardim Europa']),
           ('Presidente Venceslau', 'SP', '19400', ['Centro', 'Jardim Alvorada']),
           ('Álvares Machado', 'SP', '19160', ['Centro'])]
RUAS = ['Rua Tenente Nicolau Maffei', 'Av. Washington Luiz', 'Rua Barão do Rio Branco', 'Rua Quintino Bocaiúva', 'Av. Brasil',
        'Rua Siqueira Campos', 'Rua Rui Barbosa', 'Rua São Paulo', 'Av. Manoel Goulart', 'Rua Dom Pedro II', 'Rua Joaquim Nabuco']

def telefone(): return '18' + '9' + str(R.randint(81000000, 99999999))
def sem_acento(s):
    import unicodedata
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn')

clientes = []
for i, nome in enumerate(PF + [p[0] for p in PJ], start=1):
    pj = i > len(PF)
    cid, uf, cep, bairros = R.choice(CIDADES[:2] + CIDADES)
    if pj:
        email = 'contato@' + PJ[i - len(PF) - 1][1] + '.com.br'
    else:
        partes = sem_acento(nome).lower().split()
        email = f"{partes[0]}.{partes[-1]}{R.choice(['', str(R.randint(1, 99))])}@{R.choice(['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br'])}"
    criado = hora(dia_util(INICIO - timedelta(days=R.randint(0, 120))))
    c = dict(id=i, oficina_id=OF, tipo_pessoa='PJ' if pj else 'PF', nome=nome, documento=cnpj() if pj else cpf(),
             email=email if R.random() > .1 else None, telefone=telefone(),
             endereco=f"{R.choice(RUAS)}, {R.randint(12, 2900)}", bairro=R.choice(bairros), cidade=cid, uf=uf,
             cep=cep + str(R.randint(100, 999)), aceita_contato=int(R.random() > .15),
             observacoes=R.choice([None, None, None, 'Prefere contato por WhatsApp.', 'Cliente antigo, indicado pelo vizinho.', 'Frota da empresa, faturar no CNPJ.' if pj else 'Avisar antes de trocar peça.']),
             ativo=1, criado_em=criado)
    clientes.append(c); ins('clientes', c)

# ── Veículos ────────────────────────────────────────────────────────────────
MODELOS = {24: ('Chevrolet', 'Onix', 'Flex'), 26: ('Chevrolet', 'Prisma', 'Flex'), 31: ('Chevrolet', 'S10', 'Diesel'), 30: ('Chevrolet', 'Tracker', 'Flex'),
           40: ('Fiat', 'Argo', 'Flex'), 39: ('Fiat', 'Mobi', 'Flex'), 44: ('Fiat', 'Strada', 'Flex'), 45: ('Fiat', 'Toro', 'Diesel'), 38: ('Fiat', 'Uno', 'Flex'),
           54: ('Ford', 'EcoSport', 'Flex'), 50: ('Ford', 'Ka', 'Flex'), 55: ('Ford', 'Ranger', 'Diesel'), 60: ('Honda', 'Civic', 'Flex'), 58: ('Honda', 'Fit', 'Flex'),
           61: ('Honda', 'HR-V', 'Flex'), 66: ('Hyundai', 'Creta', 'Flex'), 64: ('Hyundai', 'HB20', 'Flex'), 71: ('Jeep', 'Compass', 'Flex'), 70: ('Jeep', 'Renegade', 'Flex'),
           93: ('Nissan', 'Kicks', 'Flex'), 113: ('Renault', 'Duster', 'Flex'), 110: ('Renault', 'Kwid', 'Flex'), 111: ('Renault', 'Sandero', 'Flex'),
           130: ('Toyota', 'Corolla', 'Flex'), 132: ('Toyota', 'Hilux', 'Diesel')}
COMB = {'Flex': 'Flex (Álcool/Gasolina)', 'Diesel': 'Diesel'}
CORES = ['Branco', 'Prata', 'Preto', 'Cinza', 'Vermelho', 'Azul', 'Branco', 'Prata']
placas = set()
def placa():
    while True:
        L = 'ABCDEFGHIJKLMNOPRSTUVWXYZ'
        p = ''.join(R.choice(L) for _ in range(3)) + str(R.randint(0, 9)) + R.choice(L) + f"{R.randint(0, 99):02d}"
        if p not in placas: placas.add(p); return p
veiculos = []
donos = list(range(1, 26)) + [21, 21, 22, 23, 4]  # frotas de PJ têm mais de um
for i, cli in enumerate(donos, start=1):
    mid = R.choice(list(MODELOS)) if cli <= 20 else R.choice([31, 44, 45, 55, 132])
    p = placa()
    texto = None
    if i in (13,):  # um modelo fora do catálogo
        mid, texto = None, 'Mitsubishi L200 Triton'
    v = dict(id=i, oficina_id=OF, cliente_id=cli, modelo_id=mid, modelo_texto=texto, placa=p[:3] + p[3:], placa_normalizada=p,
             ano=R.randint(2012, 2025), cor=R.choice(CORES), combustivel='Diesel' if texto else COMB[MODELOS[mid][2]],
             observacoes=None, ativo=1, criado_em=max(clientes[cli - 1]['criado_em'], hora(dia_util(INICIO - timedelta(days=R.randint(0, 60))))))
    veiculos.append(v); ins('veiculos', v)
veiculos_do = {}
for v in veiculos: veiculos_do.setdefault(v['cliente_id'], []).append(v)

# ── Fornecedores ────────────────────────────────────────────────────────────
FORN = [('Auto Peças Prudente Ltda', 'Sérgio'), ('Distribuidora Paulista de Autopeças', 'Renata'), ('Lubrificantes Oeste Comércio', 'Valter'),
        ('Freios & Cia Distribuidora', 'Cláudia'), ('Elétrica Automotiva Central', 'Fábio'), ('Pneus Prudente', 'Marcelo')]
for i, (n, cont) in enumerate(FORN, start=1):
    ins('fornecedores', dict(id=i, oficina_id=OF, nome=n, documento=cnpj(), contato=cont, telefone='18' + str(R.randint(32000000, 39999999)),
                             email='vendas@' + sem_acento(n.split()[0] + n.split()[1]).lower().replace('&', 'e') + '.com.br', observacoes=None, ativo=1,
                             criado_em=hora(INICIO - timedelta(days=R.randint(30, 200)))))

# ── Produtos ────────────────────────────────────────────────────────────────
# (nome, categoria, marca, custo, venda, minimo, fornecedor, unidade de compra)
PECAS = [
    ('Óleo de motor 5W30 sintético (litro)', 'Óleos e Lubrificantes', 'Mobil', 32.90, 54.90, 20, 3, 48),
    ('Óleo de motor 15W40 semissintético (litro)', 'Óleos e Lubrificantes', 'Lubrax', 22.50, 38.90, 15, 3, 36),
    ('Fluido de freio DOT 4 500ml', 'Óleos e Lubrificantes', 'Bosch', 19.90, 34.90, 6, 3, 12),
    ('Aditivo de radiador 1L', 'Óleos e Lubrificantes', 'Paraflu', 18.00, 32.00, 6, 3, 12),
    ('Filtro de óleo', 'Filtros', 'Tecfil', 18.50, 36.90, 10, 1, 24),
    ('Filtro de ar do motor', 'Filtros', 'Mann', 26.00, 49.90, 8, 1, 18),
    ('Filtro de combustível', 'Filtros', 'Tecfil', 21.00, 42.00, 6, 1, 12),
    ('Filtro de ar-condicionado', 'Filtros', 'Wega', 24.00, 45.00, 6, 1, 12),
    ('Pastilha de freio dianteira (jogo)', 'Freios', 'Cobreq', 79.00, 149.90, 6, 4, 12),
    ('Pastilha de freio traseira (jogo)', 'Freios', 'Cobreq', 69.00, 129.90, 4, 4, 8),
    ('Disco de freio dianteiro (par)', 'Freios', 'Fremax', 189.00, 329.90, 3, 4, 6),
    ('Lona de freio traseira (jogo)', 'Freios', 'Fras-le', 65.00, 119.90, 3, 4, 6),
    ('Amortecedor dianteiro', 'Suspensão', 'Cofap', 189.00, 339.90, 4, 2, 8),
    ('Amortecedor traseiro', 'Suspensão', 'Cofap', 149.00, 279.90, 4, 2, 8),
    ('Pivô de suspensão', 'Suspensão', 'Nakata', 48.00, 89.90, 4, 2, 10),
    ('Bieleta da barra estabilizadora', 'Suspensão', 'Nakata', 39.00, 74.90, 4, 2, 10),
    ('Bucha da bandeja', 'Suspensão', 'Sampel', 22.00, 44.90, 6, 2, 16),
    ('Correia dentada', 'Motor', 'Gates', 89.00, 169.90, 3, 2, 8),
    ('Tensor da correia dentada', 'Motor', 'SKF', 110.00, 199.90, 2, 2, 6),
    ('Bomba d\'água', 'Motor', 'Urba', 135.00, 239.90, 2, 2, 5),
    ('Vela de ignição', 'Motor', 'NGK', 21.00, 39.90, 16, 1, 40),
    ('Cabo de vela (jogo)', 'Motor', 'NGK', 95.00, 179.90, 2, 1, 5),
    ('Junta da tampa de válvulas', 'Motor', 'Sabó', 34.00, 64.90, 3, 2, 8),
    ('Kit de embreagem', 'Embreagem', 'LUK', 489.00, 849.90, 2, 2, 4),
    ('Bateria 60Ah', 'Sistema Elétrico', 'Moura', 389.00, 599.90, 3, 5, 6),
    ('Lâmpada farol H4', 'Sistema Elétrico', 'Philips', 18.00, 39.90, 10, 5, 20),
    ('Palheta do limpador (par)', 'Sistema Elétrico', 'Bosch', 39.00, 79.90, 4, 5, 10),
    ('Pneu 175/70 R14', 'Pneus', 'Pirelli', 329.00, 489.90, 4, 6, 8),
]
SERVICOS = [
    ('Troca de óleo e filtro', 'Manutenção preventiva', 60, 0.6),
    ('Revisão completa', 'Manutenção preventiva', 280, 3),
    ('Alinhamento e balanceamento', 'Suspensão', 120, 1),
    ('Diagnóstico eletrônico (scanner)', 'Diagnóstico', 120, 1),
    ('Troca de pastilhas de freio', 'Freios', 90, 1),
    ('Troca de discos e pastilhas', 'Freios', 160, 1.5),
    ('Revisão do sistema de freios', 'Freios', 140, 1.5),
    ('Troca de amortecedores (par)', 'Suspensão', 220, 2.5),
    ('Troca de correia dentada', 'Motor', 280, 3),
    ('Troca do kit de embreagem', 'Embreagem', 420, 5),
    ('Limpeza de bicos injetores', 'Motor', 180, 1.5),
    ('Higienização do ar-condicionado', 'Ar-condicionado', 150, 1),
    ('Troca de bateria', 'Sistema Elétrico', 40, 0.3),
    ('Mão de obra mecânica (hora)', 'Mão de obra', 150, 1),
]
produtos = []
pid = 0
for nome, cat, marca, custo, venda, minimo, forn, lote in PECAS:
    pid += 1
    produtos.append(dict(id=pid, oficina_id=OF, tipo='PECA', nome=nome, categoria=cat, codigo_interno=f"P{pid:04d}",
                         codigo_barras='789' + str(R.randint(10 ** 9, 10 ** 10 - 1)), marca=marca, descricao=None,
                         localizacao=f"{R.choice('ABCD')}-{R.randint(1, 6)}", estoque_atual=Decimal(0), estoque_minimo=Decimal(minimo),
                         preco_custo=D(custo), preco_venda=D(venda), ativo=1, criado_em=datetime.combine(INICIO - timedelta(days=20), time(9, 0)),
                         _forn=forn, _lote=lote))
for nome, cat, preco, horas in SERVICOS:
    pid += 1
    produtos.append(dict(id=pid, oficina_id=OF, tipo='SERVICO', nome=nome, categoria=cat, codigo_interno=f"S{pid:04d}",
                         codigo_barras=None, marca=None, descricao=f"Tempo médio: {str(horas).replace('.', ',')} h", localizacao=None,
                         estoque_atual=Decimal(0), estoque_minimo=Decimal(0), preco_custo=D(0), preco_venda=D(preco), ativo=1,
                         criado_em=datetime.combine(INICIO - timedelta(days=20), time(9, 0))))
P = {p['nome']: p for p in produtos}

# Pacotes de serviço → peças típicas
PACOTES = [
    (['Troca de óleo e filtro'], [('Óleo de motor 5W30 sintético (litro)', 4), ('Filtro de óleo', 1)], 'Revisão de rotina, troca de óleo vencida.', 30),
    (['Troca de óleo e filtro'], [('Óleo de motor 15W40 semissintético (litro)', 4), ('Filtro de óleo', 1), ('Filtro de ar do motor', 1)], 'Troca de óleo e filtros.', 14),
    (['Revisão completa'], [('Óleo de motor 5W30 sintético (litro)', 4), ('Filtro de óleo', 1), ('Filtro de ar do motor', 1), ('Filtro de combustível', 1), ('Vela de ignição', 4)], 'Revisão dos 60 mil km.', 10),
    (['Troca de pastilhas de freio'], [('Pastilha de freio dianteira (jogo)', 1), ('Fluido de freio DOT 4 500ml', 1)], 'Barulho ao frear, chiado na roda dianteira.', 14),
    (['Troca de discos e pastilhas'], [('Disco de freio dianteiro (par)', 1), ('Pastilha de freio dianteira (jogo)', 1)], 'Volante treme ao frear.', 7),
    (['Revisão do sistema de freios'], [('Lona de freio traseira (jogo)', 1), ('Pastilha de freio traseira (jogo)', 1), ('Fluido de freio DOT 4 500ml', 1)], 'Pedal de freio baixo.', 6),
    (['Troca de amortecedores (par)', 'Alinhamento e balanceamento'], [('Amortecedor dianteiro', 2)], 'Barulho na suspensão em lombadas.', 7),
    (['Mão de obra mecânica (hora)', 'Alinhamento e balanceamento'], [('Pivô de suspensão', 2), ('Bieleta da barra estabilizadora', 2), ('Bucha da bandeja', 2)], 'Folga na direção e barulho na dianteira.', 6),
    (['Troca de correia dentada'], [('Correia dentada', 1), ('Tensor da correia dentada', 1), ('Bomba d\'água', 1)], 'Troca preventiva da correia dentada.', 6),
    (['Troca do kit de embreagem'], [('Kit de embreagem', 1)], 'Embreagem patinando e pedal duro.', 3),
    (['Diagnóstico eletrônico (scanner)', 'Limpeza de bicos injetores'], [('Vela de ignição', 4), ('Cabo de vela (jogo)', 1)], 'Motor falhando e luz de injeção acesa.', 6),
    (['Higienização do ar-condicionado'], [('Filtro de ar-condicionado', 1)], 'Ar-condicionado com cheiro ruim.', 6),
    (['Troca de bateria'], [('Bateria 60Ah', 1)], 'Carro não pega de manhã.', 6),
    (['Diagnóstico eletrônico (scanner)'], [], 'Luz do painel acesa, cliente quer avaliação.', 6),
    (['Alinhamento e balanceamento'], [('Pneu 175/70 R14', 2)], 'Pneus gastos e carro puxando para o lado.', 5),
    (['Mão de obra mecânica (hora)'], [('Junta da tampa de válvulas', 1), ('Lâmpada farol H4', 2), ('Palheta do limpador (par)', 1)], 'Vazamento de óleo na tampa de válvulas e farol queimado.', 4),
    (['Troca de óleo e filtro', 'Alinhamento e balanceamento'], [('Óleo de motor 5W30 sintético (litro)', 4), ('Filtro de óleo', 1), ('Aditivo de radiador 1L', 1)], 'Antes de viajar, quer revisão rápida.', 6),
]
pesos_pac = [p[3] for p in PACOTES]

# ── Ordens de serviço: datas de abertura ─────────────────────────────────────
aberturas = []
d = INICIO
while d < HOJE - timedelta(days=7):
    if d.weekday() != 6:
        mes_idx = (d.year - INICIO.year) * 12 + d.month - INICIO.month  # cresce ao longo dos meses
        pr = 0.24 + 0.045 * mes_idx
        n = (1 if R.random() < pr else 0) + (1 if R.random() < pr * 0.25 else 0)
        for _ in range(n): aberturas.append(hora(d, 8, 16))
    d += timedelta(days=1)
# últimos 7 dias: perfil controlado (inclui hoje), para o gráfico de fluxo
ULT = {6: 3, 5: 2, 4: 4, 3: 2, 2: 0, 1: 5, 0: 3}
for back, n in ULT.items():
    dd = HOJE - timedelta(days=back)
    for k in range(n):
        h = hora(dd, 8, 16) if back else datetime.combine(dd, time(8 + k, R.choice([5, 20, 40])))
        aberturas.append(h)
aberturas.sort()

oss, itens, hist, mov_est = [], [], [], []
item_id = 0
numero = 0
DEFEITO_EXTRA = ['', ' Cliente pediu orçamento antes de executar.', ' Veículo usado para trabalho, prioridade.', '']

def status_para(abertura):
    idade = (AGORA - abertura).days
    r = R.random()
    if idade > 20:
        return 'ENTREGUE' if r < .86 else ('CANCELADA' if r < .93 else 'RECUSADA')
    if idade > 7:
        return 'ENTREGUE' if r < .72 else R.choice(['PRONTA', 'EM_EXECUCAO', 'RECUSADA', 'CANCELADA', 'ENTREGUE'])
    if idade > 2:
        return R.choice(['ENTREGUE', 'ENTREGUE', 'ENTREGUE', 'PRONTA', 'EM_EXECUCAO', 'APROVADA', 'ORCAMENTO'])
    return R.choice(['ORCAMENTO', 'ORCAMENTO', 'APROVADA', 'EM_EXECUCAO', 'EM_EXECUCAO', 'ENTREGUE'])

forcar = {}  # garante variedade no estado atual
for os_dt in aberturas:
    numero += 1
    st = status_para(os_dt)
    oss.append(dict(_abertura=os_dt, _status=st, numero=numero))
# ajustes: pelo menos 2 aguardando peça, 3 prontas, 1 orçamento vencido
recentes = [o for o in oss if (AGORA - o['_abertura']).days <= 12]
for o, s in zip(recentes[-12:], ['AGUARDANDO_PECA', 'PRONTA', 'AGUARDANDO_PECA', 'PRONTA', 'EM_EXECUCAO', 'APROVADA', 'PRONTA']):
    o['_status'] = s
velhos_orc = [o for o in oss if 14 <= (AGORA - o['_abertura']).days <= 25]
if velhos_orc: velhos_orc[0]['_status'] = 'ORCAMENTO'
# entregas hoje e nos últimos dias (para a linha de receita)
for o in oss:
    if HOJE - timedelta(days=9) <= o['_abertura'].date() <= HOJE - timedelta(days=1) and o['_status'] == 'ENTREGUE':
        pass

aguardando_produtos = set()
hist_id = 0
def H(os_id, acao, de, para, quando, autor, motivo=None, dados=None):
    global hist_id
    hist_id += 1
    hist.append(dict(id=hist_id, oficina_id=OF, ordem_servico_id=os_id, lancamento_id=None, cliente_id=None, veiculo_id=None, produto_id=None,
                     caixa_id=None, acao=acao, estado_anterior=de, estado_novo=para, motivo=motivo,
                     dados=json.dumps(dados, ensure_ascii=False) if dados is not None else None, autor_id=autor, criado_em=quando))

ultima_km = {}
for idx, o in enumerate(oss, start=1):
    os_dt = o['_abertura']; st = o['_status']
    cli = R.choice(range(1, 26)) if R.random() < .75 else R.choice([1, 2, 3, 5, 8, 21, 22, 4])
    v = R.choice(veiculos_do[cli])
    pac = R.choices(PACOTES, weights=pesos_pac)[0]
    servs, pecas, defeito, _ = pac
    tecnico = R.choice(TECS)
    criador = R.choice(BALCAO)
    km = ultima_km.get(v['id'], R.randint(25, 140) * 1000) + R.randint(3000, 12000)
    ultima_km[v['id']] = km
    o.update(id=idx, oficina_id=OF, cliente_id=cli, veiculo_id=v['id'], tecnico_id=None if st == 'ORCAMENTO' and R.random() < .5 else tecnico,
             status=st, km_veiculo=km, defeito_relatado=defeito + R.choice(DEFEITO_EXTRA), diagnostico=None, observacoes=None,
             observacoes_internas=None, checklist_entrega=None, validade_orcamento=(os_dt + timedelta(days=10)).date(),
             previsao_conclusao=None, aprovado_em=None, aprovado_por_nome=None, assinatura_cliente=None, valor_aprovado=None,
             recusado_em=None, motivo_recusa=None, concluido_em=None, conferido_por=None, conferido_em=None, entregue_em=None,
             cancelado_em=None, motivo_cancelamento=None, token_rastreio=hashlib.md5(f"os{idx}-{os_dt}".encode()).hexdigest(),
             criado_por=criador, criado_em=os_dt)
    # itens
    lista = [(P[s], 1) for s in servs] + [(P[n], q_) for n, q_ in pecas]
    if R.random() < .25 and 'Troca de óleo e filtro' not in servs:
        lista.append((P['Troca de óleo e filtro'], 1)); lista += [(P['Óleo de motor 5W30 sintético (litro)'], 4), (P['Filtro de óleo'], 1)]
    o_itens = []
    for prod, qtd in lista:
        item_id += 1
        unit = prod['preco_venda']
        if prod['nome'] == 'Mão de obra mecânica (hora)': qtd = R.choice([1, 1.5, 2, 3])
        desc = D(0)
        o_itens.append(dict(id=item_id, oficina_id=OF, ordem_servico_id=idx, tipo=prod['tipo'], origem='ORCAMENTO', produto_id=prod['id'],
                            descricao=prod['nome'], categoria=prod['categoria'], quantidade=Decimal(str(qtd)), valor_unitario=unit, desconto=desc,
                            custo_unitario=prod['preco_custo'] if prod['tipo'] == 'PECA' else None,
                            tecnico_id=(o['tecnico_id'] if prod['tipo'] == 'SERVICO' else None), comissao_percentual=D(0), comissao_valor=D(0),
                            comissao_lancamento_id=None, estoque_status='NAO_SE_APLICA' if prod['tipo'] == 'SERVICO' else 'PENDENTE',
                            executado=0, criado_em=os_dt + timedelta(minutes=R.randint(3, 25))))
    # desconto comercial ocasional no primeiro serviço
    if R.random() < .2:
        s0 = next(i for i in o_itens if i['tipo'] == 'SERVICO')
        s0['desconto'] = D(R.choice([10, 15, 20, 30]))
    H(idx, 'CRIACAO', None, 'ORCAMENTO', os_dt, criador)
    t = os_dt
    if st in ('RECUSADA',):
        t = mais_horas(t, 4, 40); o['recusado_em'] = t
        o['motivo_recusa'] = R.choice(['Cliente achou caro, vai pensar.', 'Vai fazer em outra oficina.', 'Vendeu o carro.', 'Sem orçamento este mês.'])
        H(idx, 'STATUS', 'ORCAMENTO', 'RECUSADA', t, criador, o['motivo_recusa'])
    elif st == 'CANCELADA' and R.random() < .5:
        t = mais_horas(t, 2, 30); o['cancelado_em'] = t
        o['motivo_cancelamento'] = R.choice(['Cliente desistiu do serviço.', 'Orçamento duplicado por engano.'])
        H(idx, 'STATUS', 'ORCAMENTO', 'CANCELADA', t, criador, o['motivo_cancelamento'])
    elif st != 'ORCAMENTO':
        t = mais_horas(t, 0.5, 20)
        if t > AGORA: t = AGORA - timedelta(minutes=R.randint(20, 90))
        nome_cli = clientes[cli - 1]['nome']
        o['aprovado_em'] = t; o['aprovado_por_nome'] = nome_cli.split()[0] + ' ' + nome_cli.split()[-1] if clientes[cli - 1]['tipo_pessoa'] == 'PF' else 'Responsável da frota'
        o['previsao_conclusao'] = mais_horas(t, 6, 50)
        o['_aprov_itens'] = True
        aprov_t = t
        if st != 'APROVADA':
            t = mais_horas(t, 0.5, 6)
            if t > AGORA: t = AGORA - timedelta(minutes=R.randint(5, 40))
            o['_inicio'] = t
            # peças baixadas na hora do início (exceto as que faltam)
            for it in o_itens:
                if it['tipo'] == 'PECA': it['estoque_status'] = 'BAIXADO'; it['_baixa'] = t
            if st == 'AGUARDANDO_PECA':
                falta = next((i for i in o_itens if i['tipo'] == 'PECA'), None)
                if falta is None:
                    item_id += 1
                    prod = P['Amortecedor traseiro']
                    falta = dict(id=item_id, oficina_id=OF, ordem_servico_id=idx, tipo='PECA', origem='ORCAMENTO', produto_id=prod['id'], descricao=prod['nome'],
                                 categoria=prod['categoria'], quantidade=Decimal(2), valor_unitario=prod['preco_venda'], desconto=D(0), custo_unitario=prod['preco_custo'],
                                 tecnico_id=None, comissao_percentual=D(0), comissao_valor=D(0), comissao_lancamento_id=None, estoque_status='PENDENTE',
                                 executado=0, criado_em=os_dt + timedelta(minutes=30))
                    o_itens.append(falta)
                falta['estoque_status'] = 'PENDENTE'; falta.pop('_baixa', None)
                aguardando_produtos.add(falta['produto_id']); o['_falta'] = falta
            if st == 'CANCELADA':
                t2 = mais_horas(t, 2, 20)
                for it in o_itens:
                    if it.get('_baixa'): it['estoque_status'] = 'DEVOLVIDO'; it['_devolve'] = t2
                o['cancelado_em'] = t2; o['motivo_cancelamento'] = 'Cliente desistiu durante a execução.'
            if st in ('PRONTA', 'ENTREGUE'):
                t = mais_horas(t, 2, 30)
                if t > AGORA: t = AGORA - timedelta(minutes=R.randint(2, 20))
                o['concluido_em'] = t
                for it in o_itens: it['executado'] = 1
                o['diagnostico'] = R.choice(['Serviço executado conforme orçamento.', 'Peças substituídas e testadas em rodagem.', 'Executado. Recomendado revisar pneus na próxima visita.', 'Teste de rodagem ok.'])
            if st == 'ENTREGUE':
                t = mais_horas(t, 1, 26)
                if t > AGORA: t = AGORA - timedelta(minutes=R.randint(1, 10))
                o['conferido_por'] = R.choice(ADMINS[:2]); o['conferido_em'] = mais_horas(o['concluido_em'], 0.2, 1) if mais_horas(o['concluido_em'], 0.2, 1) < t else t
                o['entregue_em'] = t
                o['checklist_entrega'] = 'Veículo lavado; peças antigas entregues ao cliente; nível de óleo conferido.'
    # totais
    validos = [i for i in o_itens if i['estoque_status'] != 'DEVOLVIDO']
    o['valor_pecas'] = sum((D(i['quantidade'] * i['valor_unitario']) for i in validos if i['tipo'] == 'PECA'), D(0))
    o['valor_servicos'] = sum((D(i['quantidade'] * i['valor_unitario']) for i in validos if i['tipo'] == 'SERVICO'), D(0))
    o['valor_desconto'] = sum((i['desconto'] for i in validos), D(0))
    o['valor_total'] = o['valor_pecas'] + o['valor_servicos'] - o['valor_desconto']
    if o.get('_aprov_itens'):
        o['valor_aprovado'] = o['valor_total']
        H(idx, 'APROVACAO', 'ORCAMENTO', 'APROVADA', o['aprovado_em'], criador, o['aprovado_por_nome'],
          {'valor_aprovado': str(o['valor_total']), 'itens': [{'id': i['id'], 'tipo': i['tipo'], 'descricao': i['descricao'], 'quantidade': str(i['quantidade']), 'valor_unitario': str(i['valor_unitario'])} for i in o_itens]})
        if o.get('_inicio'):
            H(idx, 'STATUS', 'APROVADA', 'EM_EXECUCAO', o['_inicio'], tecnico)
            if st == 'AGUARDANDO_PECA':
                H(idx, 'STATUS', 'EM_EXECUCAO', 'AGUARDANDO_PECA', o['_inicio'], tecnico, 'Sem estoque: ' + o['_falta']['descricao'])
            if st == 'CANCELADA':
                H(idx, 'STATUS', 'EM_EXECUCAO', 'CANCELADA', o['cancelado_em'], criador, o['motivo_cancelamento'])
        if o['concluido_em']:
            H(idx, 'STATUS', 'EM_EXECUCAO', 'PRONTA', o['concluido_em'], tecnico)
        if o['entregue_em']:
            H(idx, 'STATUS', 'PRONTA', 'ENTREGUE', o['entregue_em'], o['conferido_por'])
    if st == 'ORCAMENTO' and o['validade_orcamento'] < HOJE:
        pass
    o['atualizado_em'] = max([x for x in (o['aprovado_em'], o['recusado_em'], o.get('_inicio'), o['concluido_em'], o['entregue_em'], o['cancelado_em']) if x] or [None]) if any((o['aprovado_em'], o['recusado_em'], o['cancelado_em'])) else None
    o['_itens'] = o_itens
    itens.extend(o_itens)

# ── Estoque: compras iniciais + reposições, saídas por OS ──────────────────────
saidas = {}  # produto -> lista (quando, qtd, item)
for it in itens:
    if it['tipo'] == 'PECA' and it.get('_baixa'):
        saidas.setdefault(it['produto_id'], []).append((it['_baixa'], it['quantidade'], it))
lanc, movf = [], []
lanc_id = 0; movf_id = 0; me_id = 0

compras = []  # (quando, fornecedor, [(produto, qtd, custo)])
by_forn = {}
for p in produtos:
    if p['tipo'] == 'PECA': by_forn.setdefault(p['_forn'], []).append(p)
# compra inicial (estoque de abertura) por fornecedor
for f, ps in by_forn.items():
    quando = datetime.combine(INICIO - timedelta(days=3), time(10 + f % 5, 0))
    compras.append([quando, f, [(p, Decimal(p['_lote']), p['preco_custo']) for p in ps]])
# simula o saldo dia a dia e repõe quando cai abaixo do mínimo
eventos = sorted([(q_[0], pid_, q_[1]) for pid_, l in saidas.items() for q_ in l])
saldo = {p['id']: Decimal(0) for p in produtos if p['tipo'] == 'PECA'}
for c in compras:
    for p, qtd, _ in c[2]: saldo[p['id']] += qtd
pend_repos = {}
for quando, pid_, qtd in eventos:
    if saldo[pid_] < qtd:  # repõe antes (compra no dia anterior)
        p = produtos[pid_ - 1]
        lote = Decimal(p['_lote'])
        compras.append([datetime.combine((quando - timedelta(days=1)).date(), time(9, 30)), p['_forn'], [(p, lote, p['preco_custo'])]])
        saldo[pid_] += lote
    saldo[pid_] -= qtd
    p = produtos[pid_ - 1]
    dia_c = dia_util((quando + timedelta(days=1)).date())
    if saldo[pid_] <= p['estoque_minimo'] and dia_c < HOJE:
        lote = Decimal(p['_lote'])
        compras.append([datetime.combine(dia_c, time(14, 0)), p['_forn'], [(p, lote, p['preco_custo'])]])
        saldo[pid_] += lote
# produtos de "aguardando peça": zera o saldo com um ajuste de inventário recente (peça que acabou)
ajustes = []
for pid_ in aguardando_produtos:
    if saldo[pid_] > 0:
        ajustes.append((datetime.combine(HOJE - timedelta(days=9), time(17, 10)), pid_, -saldo[pid_], 'Inventário: peças avariadas descartadas'))
        saldo[pid_] = Decimal(0)
# alguns produtos abaixo do mínimo (alerta de estoque)
for nome in ['Lâmpada farol H4', 'Fluido de freio DOT 4 500ml', 'Palheta do limpador (par)']:
    p = P[nome]
    alvo = max(Decimal(0), p['estoque_minimo'] - Decimal(R.randint(1, 3)))
    if saldo[p['id']] > alvo:
        ajustes.append((datetime.combine(HOJE - timedelta(days=4), time(17, 30)), p['id'], alvo - saldo[p['id']], 'Inventário: contagem do mês'))
        saldo[p['id']] = alvo

compras.sort(key=lambda c: c[0])
# agrupa reposições do mesmo fornecedor no mesmo dia numa nota só
notas = {}
for quando, f, lst in compras:
    k = (quando.date(), f)
    if k in notas: notas[k][2].extend(lst)
    else: notas[k] = [quando, f, list(lst)]
nf = 1000
pagar_compras = []
for k in sorted(notas):
    quando, f, lst = notas[k]
    nf += R.randint(3, 40)
    total = sum((D(q_ * c) for _, q_, c in lst), D(0))
    lanc_id += 1
    venc = dia_util((quando + timedelta(days=28)).date())
    l = dict(id=lanc_id, oficina_id=OF, tipo='PAGAR', categoria='PECAS', descricao=f"NF {nf} · {FORN[f - 1][0]}", grupo_id=None, parcela_numero=1, parcela_total=1,
             valor=total, desconto=D(0), acrescimo=D(0), valor_pago=D(0), competencia=quando.date().replace(day=1), vencimento=venc, status='ABERTO',
             forma_prevista='BOLETO', ordem_servico_id=None, cliente_id=None, fornecedor_id=f, funcionario_id=None, documento=str(nf), observacao=None,
             criado_por=ADMINS[0], criado_em=quando, cancelado_por=None, cancelado_em=None, motivo_cancelamento=None)
    lanc.append(l); pagar_compras.append(l)
    for p, q_, c in lst:
        me_id += 1
        mov_est.append(dict(id=me_id, oficina_id=OF, produto_id=p['id'], motivo='COMPRA', delta=q_, custo_unitario=c, ordem_servico_item_id=None,
                            fornecedor_id=f, documento=str(nf), lancamento_id=lanc_id, estorno_de_id=None, observacao=None,
                            usuario_id=ADMINS[0], criado_em=quando))
for it in sorted([i for i in itens if i.get('_baixa')], key=lambda i: i['_baixa']):
    me_id += 1
    o = oss[it['ordem_servico_id'] - 1]
    mov_est.append(dict(id=me_id, oficina_id=OF, produto_id=it['produto_id'], motivo='SAIDA_OS', delta=-it['quantidade'], custo_unitario=it['custo_unitario'],
                        ordem_servico_item_id=it['id'], fornecedor_id=None, documento=None, lancamento_id=None, estorno_de_id=None,
                        observacao=f"Baixa na OS OS-{o['numero']:04d}", usuario_id=o['tecnico_id'], criado_em=it['_baixa']))
    if it.get('_devolve'):
        me_id += 1
        mov_est.append(dict(id=me_id, oficina_id=OF, produto_id=it['produto_id'], motivo='DEVOLUCAO_OS', delta=it['quantidade'], custo_unitario=it['custo_unitario'],
                            ordem_servico_item_id=it['id'], fornecedor_id=None, documento=None, lancamento_id=None, estorno_de_id=None,
                            observacao=f"OS OS-{o['numero']:04d} cancelada", usuario_id=o['criado_por'], criado_em=it['_devolve']))
        saldo[it['produto_id']] += it['quantidade']
for quando, pid_, delta, obs in ajustes:
    me_id += 1
    mov_est.append(dict(id=me_id, oficina_id=OF, produto_id=pid_, motivo='AJUSTE', delta=delta, custo_unitario=None, ordem_servico_item_id=None,
                        fornecedor_id=None, documento=None, lancamento_id=None, estorno_de_id=None, observacao=obs, usuario_id=ADMINS[0], criado_em=quando))
mov_est.sort(key=lambda m: (m['criado_em'], m['id']))
for i, m in enumerate(mov_est, start=1): m['_novo'] = i
for m in mov_est: m['id'] = m.pop('_novo')
# saldo final = soma dos deltas
soma = {}
for m in mov_est: soma[m['produto_id']] = soma.get(m['produto_id'], Decimal(0)) + m['delta']
for p in produtos:
    if p['tipo'] == 'PECA':
        p['estoque_atual'] = soma.get(p['id'], Decimal(0))
        assert p['estoque_atual'] >= 0, p['nome']

# ── Caixas: uma sessão por semana (seg → sáb); a desta semana está aberta ─────
caixas = []
seg = INICIO - timedelta(days=INICIO.weekday())
cid = 0
while seg <= HOJE:
    cid += 1
    abre = datetime.combine(seg, time(7, 50 + R.randint(0, 9)))
    sab = seg + timedelta(days=5)
    aberto = sab >= HOJE
    caixas.append(dict(id=cid, oficina_id=OF, status='ABERTO' if aberto else 'FECHADO', aberto_por=R.choice(BALCAO), aberto_em=abre,
                       valor_abertura=D(200), observacao_abertura=None, fechado_por=None if aberto else R.choice(ADMINS[:2]),
                       fechado_em=None if aberto else datetime.combine(sab, time(12, R.randint(5, 40))), valor_esperado=None,
                       valor_fechamento=None, observacao_fechamento=None, _ini=abre, _fim=None if aberto else datetime.combine(sab, time(12, 0))))
    seg += timedelta(days=7)
def caixa_em(dt):
    for c in caixas:
        if c['_ini'] <= dt and (c['_fim'] is None or dt <= c['_fim']): return c
    return None
def ajusta_para_caixa(dt):
    c = caixa_em(dt)
    if c: return dt, c
    # domingo à tarde/sábado depois do fechamento: joga para segunda 9h
    d2 = dt.date() + timedelta(days=1)
    while d2.weekday() != 0: d2 += timedelta(days=1)
    novo = datetime.combine(d2, time(9, 10))
    return novo, caixa_em(novo)

def baixa(l, valor, forma, quando, usuario, caixa=None, taxa=D(0), desc=None):
    global movf_id
    movf_id += 1
    if caixa is None: caixa = caixa_em(quando)
    m = dict(id=movf_id, oficina_id=OF, natureza='BAIXA', direcao='ENTRADA' if l['tipo'] == 'RECEBER' else 'SAIDA', valor=valor, taxa=taxa,
             forma_pagamento=forma, lancamento_id=l['id'], caixa_id=caixa['id'] if caixa else None, estorno_de_id=None, data_movimento=quando,
             descricao=desc, usuario_id=usuario, chave_idempotencia=None, criado_em=quando)
    movf.append(m)
    l['valor_pago'] += valor
    devido = l['valor'] - l['desconto'] + l['acrescimo']
    l['status'] = 'PAGO' if l['valor_pago'] == devido else ('PARCIAL' if l['valor_pago'] > 0 else 'ABERTO')
    return m

# ── Contas a receber das OS ────────────────────────────────────────────────
formas = ['PIX'] * 5 + ['CARTAO_CREDITO'] * 3 + ['CARTAO_DEBITO'] * 2 + ['DINHEIRO'] * 2
taxa_de = {'CARTAO_CREDITO': Decimal('0.0349'), 'CARTAO_DEBITO': Decimal('0.0149')}
pend_alvos = 0
for o in oss:
    if o['status'] != 'ENTREGUE' and not (o['status'] == 'PRONTA' and R.random() < .3): continue
    if o['valor_total'] <= 0: continue
    quando = o['entregue_em'] or o['concluido_em']
    cli = clientes[o['cliente_id'] - 1]
    pj = cli['tipo_pessoa'] == 'PJ'
    forma = 'BOLETO' if pj else R.choice(formas)
    parcelas = 2 if (pj and o['valor_total'] > 500) else (3 if forma == 'CARTAO_CREDITO' and o['valor_total'] > 900 and R.random() < .5 else 1)
    if forma == 'CARTAO_CREDITO': parcelas = 1  # cartão: a maquininha recebe à vista para a oficina
    total = o['valor_total']
    partes = [D(total / parcelas)] * parcelas
    partes[-1] = total - sum(partes[:-1])
    grupo = None
    for n, val in enumerate(partes, start=1):
        lanc_id += 1
        venc = (quando.date() + timedelta(days=30 * (n - 1) + (15 if pj else 0)))
        l = dict(id=lanc_id, oficina_id=OF, tipo='RECEBER', categoria='SERVICOS', descricao=f"OS-{o['numero']:04d}" + (f" ({n}/{parcelas})" if parcelas > 1 else ''),
                 grupo_id=grupo, parcela_numero=n, parcela_total=parcelas, valor=val, desconto=D(0), acrescimo=D(0), valor_pago=D(0),
                 competencia=quando.date().replace(day=1), vencimento=venc, status='ABERTO', forma_prevista=forma, ordem_servico_id=o['id'],
                 cliente_id=o['cliente_id'], fornecedor_id=None, funcionario_id=None, documento=None, observacao=None,
                 criado_por=o['conferido_por'] or o['criado_por'], criado_em=quando, cancelado_por=None, cancelado_em=None, motivo_cancelamento=None)
        if n == 1: grupo = lanc_id
        lanc.append(l)
        # pagamento
        idade = (HOJE - venc).days
        if o['status'] == 'PRONTA':
            continue  # cobrança lançada, cliente paga na retirada
        if idade < 0 and n > 1:
            continue  # parcela futura
        if pj and idade >= 0:
            if R.random() < .82 or idade > 40:
                pago_em = datetime.combine(min(HOJE, venc + timedelta(days=R.randint(-3, 4))), time(11, 0))
                if pago_em > AGORA: pago_em = AGORA - timedelta(hours=1)
                baixa(l, val, 'BOLETO', pago_em, ADMINS[0])
            continue
        if pj: continue
        quando_pg = quando
        if pend_alvos < 3 and HOJE - timedelta(days=40) < venc < HOJE - timedelta(days=3) and R.random() < .35:
            pend_alvos += 1
            if pend_alvos == 2:
                baixa(l, D(val / 2), 'PIX', quando_pg, o['conferido_por'], desc='Entrada; restante combinado para o mês que vem')
            continue  # vencidas em aberto (alerta no painel)
        if forma == 'DINHEIRO':
            quando_pg, cx = ajusta_para_caixa(quando_pg)
            if quando_pg > AGORA: quando_pg = AGORA - timedelta(minutes=5); cx = caixa_em(quando_pg)
            baixa(l, val, 'DINHEIRO', quando_pg, o['conferido_por'], caixa=cx)
        else:
            taxa = D(val * taxa_de.get(forma, 0))
            baixa(l, val, forma, quando_pg, o['conferido_por'], taxa=taxa)

# ── Contas a pagar fixas ────────────────────────────────────────────────────
mes = INICIO.replace(day=1)
while mes <= HOJE.replace(day=1):
    prox = (mes + timedelta(days=32)).replace(day=1)
    fixas = [('ALUGUEL', 'Aluguel do galpão', D(3800), 10, 'BOLETO', None),
             ('CONTAS_CONSUMO', 'Energia elétrica', D(R.randint(520, 760)), 15, 'BOLETO', None),
             ('CONTAS_CONSUMO', 'Água e esgoto', D(R.randint(110, 170)), 18, 'BOLETO', None),
             ('CONTAS_CONSUMO', 'Internet e telefone', D(189.90), 20, 'BOLETO', None),
             ('IMPOSTOS', 'DAS Simples Nacional', D(R.randint(1100, 1900)), 20, 'BOLETO', None),
             ('SALARIO', 'Folha de pagamento', D(9600), 5, 'TRANSFERENCIA', None)]
    if mes.month in (7, 10): fixas.append(('FERRAMENTAS', 'Elevador automotivo: manutenção' if mes.month == 7 else 'Jogo de chaves e torquímetro', D(1450 if mes.month == 7 else 890), 12, 'PIX', None))
    for cat, desc, val, dia, forma, _ in fixas:
        venc = dia_util(prox.replace(day=dia) if cat in ('SALARIO', 'IMPOSTOS') else mes.replace(day=dia))
        comp = mes
        if venc > HOJE + timedelta(days=25): continue
        lanc_id += 1
        l = dict(id=lanc_id, oficina_id=OF, tipo='PAGAR', categoria=cat, descricao=f"{desc} · {comp.strftime('%m/%Y')}", grupo_id=None, parcela_numero=1, parcela_total=1,
                 valor=val, desconto=D(0), acrescimo=D(0), valor_pago=D(0), competencia=comp, vencimento=venc, status='ABERTO', forma_prevista=forma,
                 ordem_servico_id=None, cliente_id=None, fornecedor_id=None, funcionario_id=None, documento=None, observacao=None,
                 criado_por=ADMINS[0], criado_em=datetime.combine(comp, time(9, 0)), cancelado_por=None, cancelado_em=None, motivo_cancelamento=None)
        lanc.append(l)
        if venc < HOJE - timedelta(days=2) or (venc <= HOJE and R.random() < .5):
            if cat == 'CONTAS_CONSUMO' and desc.startswith('Energia') and mes == HOJE.replace(day=1):
                continue  # uma conta vencida (alerta)
            atraso = R.random() < .1
            if atraso:
                l['acrescimo'] = D(val * Decimal('0.02'))
            baixa(l, l['valor'] + l['acrescimo'], forma, datetime.combine(venc + timedelta(days=R.randint(3, 6) if atraso else 0), time(10, 30)), ADMINS[0])
    mes = prox
# compras de peças: pagas no vencimento (as recentes ficam em aberto)
for l in pagar_compras:
    if l['vencimento'] < HOJE - timedelta(days=1):
        baixa(l, l['valor'], 'BOLETO', datetime.combine(l['vencimento'], time(10, 0)), ADMINS[0])
# competencia = dia 1 do mês
# ── Movimentos de gaveta: sangrias e suprimentos ─────────────────────────────
for c in caixas:
    if R.random() < .35:
        movf_id += 1
        quando = c['_ini'] + timedelta(days=R.randint(1, 3), hours=R.randint(2, 6))
        if quando > AGORA: continue
        movf.append(dict(id=movf_id, oficina_id=OF, natureza='SUPRIMENTO', direcao='ENTRADA', valor=D(100), taxa=D(0), forma_pagamento='DINHEIRO',
                         lancamento_id=None, caixa_id=c['id'], estorno_de_id=None, data_movimento=quando, descricao='Troco', usuario_id=c['aberto_por'],
                         chave_idempotencia=None, criado_em=quando))
# esperado de cada caixa e sangria antes do fechamento
for c in caixas:
    esperado = c['valor_abertura']
    for m in movf:
        if m['caixa_id'] == c['id'] and m['forma_pagamento'] == 'DINHEIRO':
            esperado += m['valor'] if m['direcao'] == 'ENTRADA' else -m['valor']
    if c['status'] == 'FECHADO':
        if esperado > 600:
            movf_id += 1
            s = D(int((esperado - 200) / 50) * 50)
            quando = c['_fim'] - timedelta(minutes=20)
            movf.append(dict(id=movf_id, oficina_id=OF, natureza='SANGRIA', direcao='SAIDA', valor=s, taxa=D(0), forma_pagamento='DINHEIRO',
                             lancamento_id=None, caixa_id=c['id'], estorno_de_id=None, data_movimento=quando, descricao='Depósito no banco',
                             usuario_id=c['fechado_por'], chave_idempotencia=None, criado_em=quando))
            esperado -= s
        c['valor_esperado'] = esperado
        dif = R.choice([D(0)] * 8 + [D(-5), D(2.5)])
        c['valor_fechamento'] = esperado + dif
        if dif: c['observacao_fechamento'] = 'Diferença de troco.' if dif < 0 else 'Sobra de troco.'
movf.sort(key=lambda m: (m['data_movimento'], m['id']))
mapa = {}
for i, m in enumerate(movf, start=1): mapa[m['id']] = i
for m in movf: m['id'] = mapa[m['id']]

# ── Agendamentos ────────────────────────────────────────────────────────────
ag = []
agid = 0
SERV_AG = ['Troca de óleo e filtro', 'Revisão completa', 'Alinhamento e balanceamento', 'Diagnóstico eletrônico (scanner)', 'Revisão do sistema de freios',
           'Higienização do ar-condicionado', 'Troca de correia dentada', 'Avaliação de barulho na suspensão']
for o in oss:
    if o['_abertura'] > datetime.combine(HOJE - timedelta(days=45), time(0)) and R.random() < .3:
        agid += 1
        ag.append(dict(id=agid, oficina_id=OF, cliente_id=o['cliente_id'], veiculo_id=o['veiculo_id'], tecnico_id=o['tecnico_id'],
                       data_hora=o['_abertura'].replace(minute=0) if o['_abertura'].minute < 30 else o['_abertura'].replace(minute=30),
                       duracao_min=R.choice([60, 90, 120]), servico=o['_itens'][0]['descricao'], observacoes=None, status='CONCLUIDO',
                       ordem_servico_id=o['id'], criado_por=R.choice(BALCAO), criado_em=o['_abertura'] - timedelta(days=R.randint(2, 6))))
for back in (30, 22, 12, 6):
    agid += 1; cli = R.randint(1, 20); v = R.choice(veiculos_do[cli])
    dd = dia_util(HOJE - timedelta(days=back))
    ag.append(dict(id=agid, oficina_id=OF, cliente_id=cli, veiculo_id=v['id'], tecnico_id=R.choice(TECS), data_hora=datetime.combine(dd, time(R.choice([8, 9, 14, 15]), 0)),
                   duracao_min=60, servico=R.choice(SERV_AG), observacoes=None, status='FALTOU' if back % 2 == 0 else 'CANCELADO',
                   ordem_servico_id=None, criado_por=R.choice(BALCAO), criado_em=datetime.combine(dd - timedelta(days=4), time(10, 0))))
futuros = [(0, 14, 'CONFIRMADO'), (0, 16, 'AGENDADO'), (1, 8, 'CONFIRMADO'), (1, 10, 'AGENDADO'), (1, 15, 'AGENDADO'), (2, 9, 'CONFIRMADO'),
           (3, 8, 'AGENDADO'), (3, 13, 'AGENDADO'), (4, 10, 'AGENDADO'), (6, 9, 'AGENDADO'), (8, 14, 'AGENDADO'), (10, 8, 'AGENDADO')]
for dd_, hh, stt in futuros:
    agid += 1; cli = R.randint(1, 25); v = R.choice(veiculos_do[cli])
    dd = dia_util(HOJE + timedelta(days=dd_))
    ag.append(dict(id=agid, oficina_id=OF, cliente_id=cli, veiculo_id=v['id'], tecnico_id=R.choice(TECS + [None]), data_hora=datetime.combine(dd, time(hh, R.choice([0, 30]))),
                   duracao_min=R.choice([60, 60, 90, 120, 180]), servico=R.choice(SERV_AG),
                   observacoes=R.choice([None, None, 'Cliente vai deixar o carro e buscar no fim do dia.', 'Trazer nota da última revisão.']), status=stt,
                   ordem_servico_id=None, criado_por=R.choice(BALCAO), criado_em=AGORA - timedelta(days=R.randint(1, 6), hours=R.randint(0, 5))))
ag.sort(key=lambda a: a['data_hora'])
for i, a in enumerate(ag, start=1): a['id'] = i

# ── Notificações ────────────────────────────────────────────────────────────
notif = []
nid = 0
for o in oss:
    if o['status'] == 'AGUARDANDO_PECA':
        for u in ADMINS:
            nid += 1
            notif.append(dict(id=nid, oficina_id=OF, usuario_id=u, tipo='PECA_FALTANDO', titulo=f"OS OS-{o['numero']:04d} aguardando peça",
                              mensagem='Sem estoque: ' + o['_falta']['descricao'], link=f"./os?id={o['id']}", chave_idempotencia=f"PECA:{o['id']}:seed",
                              lida_em=None, criada_em=o['_inicio']))

# ── Oficina: próximo número de OS ────────────────────────────────────────────
proximo = len(oss) + 1

# ── SQL ─────────────────────────────────────────────────────────────────────
COLS = {
 'clientes': 'id oficina_id tipo_pessoa nome documento email telefone endereco bairro cidade uf cep aceita_contato observacoes ativo criado_em',
 'veiculos': 'id oficina_id cliente_id modelo_id modelo_texto placa placa_normalizada ano cor combustivel observacoes ativo criado_em',
 'fornecedores': 'id oficina_id nome documento contato telefone email observacoes ativo criado_em',
 'produtos': 'id oficina_id tipo nome categoria codigo_interno codigo_barras marca descricao localizacao estoque_atual estoque_minimo preco_custo preco_venda ativo criado_em',
 'ordens_servico': 'id oficina_id numero cliente_id veiculo_id tecnico_id status km_veiculo defeito_relatado diagnostico observacoes observacoes_internas checklist_entrega validade_orcamento previsao_conclusao aprovado_em aprovado_por_nome assinatura_cliente valor_aprovado recusado_em motivo_recusa concluido_em conferido_por conferido_em entregue_em cancelado_em motivo_cancelamento valor_pecas valor_servicos valor_desconto valor_total token_rastreio criado_por criado_em atualizado_em',
 'ordem_servico_itens': 'id oficina_id ordem_servico_id tipo origem produto_id descricao categoria quantidade valor_unitario desconto custo_unitario tecnico_id comissao_percentual comissao_valor comissao_lancamento_id estoque_status executado criado_em',
 'lancamentos': 'id oficina_id tipo categoria descricao grupo_id parcela_numero parcela_total valor desconto acrescimo valor_pago competencia vencimento status forma_prevista ordem_servico_id cliente_id fornecedor_id funcionario_id documento observacao criado_por criado_em cancelado_por cancelado_em motivo_cancelamento',
 'movimentacoes_estoque': 'id oficina_id produto_id motivo delta custo_unitario ordem_servico_item_id fornecedor_id documento lancamento_id estorno_de_id observacao usuario_id criado_em',
 'caixas': 'id oficina_id status aberto_por aberto_em valor_abertura observacao_abertura fechado_por fechado_em valor_esperado valor_fechamento observacao_fechamento',
 'movimentacoes_financeiras': 'id oficina_id natureza direcao valor taxa forma_pagamento lancamento_id caixa_id estorno_de_id data_movimento descricao usuario_id chave_idempotencia criado_em',
 'agendamentos': 'id oficina_id cliente_id veiculo_id tecnico_id data_hora duracao_min servico observacoes status ordem_servico_id criado_por criado_em',
 'historico': 'id oficina_id ordem_servico_id lancamento_id cliente_id veiculo_id produto_id caixa_id acao estado_anterior estado_novo motivo dados autor_id criado_em',
 'notificacoes': 'id oficina_id usuario_id tipo titulo mensagem link chave_idempotencia lida_em criada_em',
}
def bloco(tabela, regs):
    cols = COLS[tabela].split()
    out = []
    for i in range(0, len(regs), 60):
        vals = ',\n'.join('(' + ','.join(q(r.get(c)) for c in cols) + ')' for r in regs[i:i + 60])
        out.append(f"INSERT INTO {tabela} ({', '.join(cols)}) VALUES\n{vals};")
    return '\n'.join(out)

# OS: os itens precisam existir antes do recalculo; a OS é inserida primeiro
for o in oss:
    o['oficina_id'] = OF
caixas_sql = [dict(c) for c in caixas]
sql = []
sql.append('-- Mecanize v2: limpeza e dados de teste (oficina 1). Mantém oficinas, usuarios e modelos_veiculos.')
sql.append('SET NAMES utf8mb4;')
sql.append('START TRANSACTION;')
sql.append('SET FOREIGN_KEY_CHECKS = 0;')
for t in ['notificacoes', 'historico', 'anexos', 'movimentacoes_financeiras', 'movimentacoes_estoque', 'agendamentos', 'ordem_servico_itens', 'lancamentos',
          'ordens_servico', 'caixas', 'produtos', 'veiculos', 'fornecedores', 'clientes']:
    sql.append(f'DELETE FROM {t};')
sql.append('SET FOREIGN_KEY_CHECKS = 1;')
sql.append(bloco('clientes', clientes))
sql.append(bloco('veiculos', veiculos))
sql.append(bloco('fornecedores', [r for t, r in linhas if t == 'fornecedores']))
sql.append(bloco('produtos', produtos))
sql.append(bloco('ordens_servico', oss))
sql.append(bloco('ordem_servico_itens', itens))
sql.append(bloco('caixas', caixas_sql))
lanc.sort(key=lambda l: l['id'])
sql.append(bloco('lancamentos', lanc))
sql.append(bloco('movimentacoes_estoque', mov_est))
sql.append(bloco('movimentacoes_financeiras', movf))
sql.append(bloco('agendamentos', ag))
sql.append(bloco('historico', hist))
sql.append(bloco('notificacoes', notif))
sql.append(f"UPDATE oficinas SET proximo_numero_os = {proximo} WHERE id = 1;")
sql.append(f'{FIM};')
print('\n'.join(sql))

# resumo para conferência (stderr)
from collections import Counter
st = Counter(o['status'] for o in oss)
print(f"clientes={len(clientes)} veiculos={len(veiculos)} produtos={len(produtos)} os={len(oss)} itens={len(itens)} lanc={len(lanc)} movf={len(movf)} mest={len(mov_est)} caixas={len(caixas)} ag={len(ag)} hist={len(hist)} notif={len(notif)}", file=sys.stderr)
print(dict(st), file=sys.stderr)
ult7 = {}
for o in oss:
    if o['criado_em'].date() >= HOJE - timedelta(days=6): ult7.setdefault(o['criado_em'].date(), [0, D(0)])[0] += 1
    if o['entregue_em'] and o['entregue_em'].date() >= HOJE - timedelta(days=6): ult7.setdefault(o['entregue_em'].date(), [0, D(0)])[1] += o['valor_total']
print(sorted((k.isoformat(), v) for k, v in ult7.items()), file=sys.stderr)
meses = Counter()
for o in oss:
    if o['entregue_em']: meses[o['entregue_em'].strftime('%Y-%m')] += o['valor_total']
print(sorted(meses.items()), file=sys.stderr)
print('abaixo do minimo:', [p['nome'] for p in produtos if p['tipo'] == 'PECA' and p['estoque_atual'] <= p['estoque_minimo']], file=sys.stderr)
print('vencidos receber:', [(l['descricao'], l['status']) for l in lanc if l['tipo'] == 'RECEBER' and l['status'] in ('ABERTO', 'PARCIAL') and l['vencimento'] < HOJE], file=sys.stderr)
print('vencidos pagar:', [(l['descricao']) for l in lanc if l['tipo'] == 'PAGAR' and l['status'] in ('ABERTO', 'PARCIAL') and l['vencimento'] < HOJE], file=sys.stderr)
