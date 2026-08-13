<?php
/*
|--------------------------------------------------------------------------
| VIEW: editUsuario
|--------------------------------------------------------------------------
| Variáveis esperadas:
| $usuario — array com os dados do usuário
|--------------------------------------------------------------------------
*/
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">
            Editar Usuário
            <?php if (!empty($usuario['nome'])): ?>
                <span style="color: var(--color-text-muted); font-weight: 500; font-size: 18px;">
                    · <?= htmlspecialchars($usuario['nome']) ?>
                </span>
            <?php endif; ?>
        </h1>
        <p class="page-header__subtitle">Atualize as informações e permissões do usuário.</p>
    </div>

    <div class="page-header__actions">
        <?php
        $ativo     = $usuario['ativo'] ?? 1;
        $pillLabel = $ativo ? 'Ativo' : 'Inativo';
        $pillColor = $ativo ? 'var(--color-success)' : 'var(--color-danger)';
        $pillBg    = $ativo ? 'var(--color-success-soft)' : 'var(--color-danger-soft)';
        ?>
        <span class="status-pill" style="background: <?= $pillBg ?>; color: <?= $pillColor ?>;">
            <span class="dot" style="background: <?= $pillColor ?>;"></span>
            <?= $pillLabel ?>
        </span>

        <a href="./listUsuarios" class="btn btn--ghost">
            <i class="bi bi-arrow-left"></i>
            Voltar
        </a>
    </div>
</div>

<form action="./editUsuario" method="POST">

    <input type="hidden" name="id_usuario" value="<?= $usuario['id_usuario'] ?>">

    <?php if (!empty($error)): ?>
        <?php $alertaTipo = 'erro'; $alertaMensagem = $error;
                  require __DIR__ . '/../layout/_alerta.php'; ?>
    <?php endif; ?>

    <div class="usr-edit-grid">

        <!-- 1. INFORMAÇÕES PESSOAIS -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-person"></i>
                    </span>
                    1. Informações Pessoais
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label>Nome Completo</label>
                        <input
                            type="text"
                            name="nome"
                            class="js-capitalize-words"
                            autocapitalize="words"
                            value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>"
                            placeholder="Nome completo do usuário"
                            required>
                    </div>

                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label>E-mail</label>
                        <input
                            type="email"
                            name="email"
                            value="<?= htmlspecialchars($usuario['email'] ?? '') ?>"
                            placeholder="email@exemplo.com"
                            required>
                    </div>

                    <div class="form-field" style="grid-column: 1 / -1;">
                        <label>Nova Senha</label>
                        <input
                            type="password"
                            name="senha"
                            placeholder="Deixe em branco para manter a senha atual">
                        <small style="display:block; margin-top:6px; color:var(--color-text-muted); font-size:12px;">
                            A senha atual será mantida se este campo ficar vazio.
                        </small>
                    </div>

                </div>
            </div>
        </div>

        <!-- 2. CONFIGURAÇÕES -->
        <div class="card">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-sliders"></i>
                    </span>
                    2. Configurações do Usuário
                </div>
            </div>

            <div class="card__body">
                <div class="form-grid">

                    <div class="form-field">
                        <label>Tipo de Usuário</label>
                        <select name="tipo" required>
                            <option value="ADMIN" <?= ($usuario['tipo'] ?? '') == 'ADMIN'     ? 'selected' : '' ?>>Admin</option>
                            <option value="TECNICO" <?= ($usuario['tipo'] ?? '') == 'TECNICO'   ? 'selected' : '' ?>>Técnico</option>
                            <option value="ATENDENTE" <?= ($usuario['tipo'] ?? '') == 'ATENDENTE' ? 'selected' : '' ?>>Atendente</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Status</label>
                        <select name="ativo" required>
                            <option value="1" <?= ($usuario['ativo'] ?? 1) == 1 ? 'selected' : '' ?>>Ativo</option>
                            <option value="0" <?= ($usuario['ativo'] ?? 1) == 0 ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>

                </div>

                <!-- Aviso de segurança -->
                <div class="usr-security-tip">
                    <div class="usr-security-tip__icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <div class="usr-security-tip__title">Informação de Segurança</div>
                        <div class="usr-security-tip__body">
                            Ao alterar a senha, o usuário precisará realizar login novamente no sistema.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- 3. RESUMO DA CONTA -->
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card__header">
                <div class="card__title">
                    <span class="ico-wrap ico" aria-hidden="true">
                        <i class="bi bi-person-badge"></i>
                    </span>
                    3. Resumo da Conta
                </div>
            </div>

            <div class="card__body">
                <div class="usr-summary-grid">

                    <div class="usr-summary-item">
                        <div class="usr-summary-item__label">ID do Usuário</div>
                        <div class="usr-summary-item__value">#<?= $usuario['id_usuario'] ?></div>
                    </div>

                    <div class="usr-summary-item">
                        <div class="usr-summary-item__label">Tipo</div>
                        <div class="usr-summary-item__value"><?= htmlspecialchars($usuario['tipo'] ?? '—') ?></div>
                    </div>

                    <div class="usr-summary-item">
                        <div class="usr-summary-item__label">Última Atualização</div>
                        <div class="usr-summary-item__value">
                            <?= !empty($usuario['atualizado_em'])
                                ? date('d/m/Y H:i', strtotime($usuario['atualizado_em']))
                                : date('d/m/Y') ?>
                        </div>
                    </div>

                    <div class="usr-summary-item">
                        <div class="usr-summary-item__label">Criado em</div>
                        <div class="usr-summary-item__value">
                            <?= !empty($usuario['criado_em'])
                                ? date('d/m/Y H:i', strtotime($usuario['criado_em']))
                                : date('d/m/Y') ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div><!-- /.usr-edit-grid -->

    <!-- FOOTER DE AÇÕES -->
    <div class="os-edit-footer">

        <!--
          O botão fica aqui pelo layout, mas pertence ao formulário
          "form-excluir-usuario", declarado depois do </form> desta tela — é
          para isso que serve o atributo form= do HTML5. Sem ele, a alternativa
          seria um <form> dentro de outro, que o navegador ignora.
          Excluir é POST porque muda dado: assim entra no controle de CSRF,
          que só vale para POST.
        -->
        <button type="submit"
            form="form-excluir-usuario"
            class="btn btn--danger-ghost">
            <i class="bi bi-person-x"></i>
            Excluir Usuário
        </button>

        <div class="os-edit-footer__right">
            <a href="./listUsuarios" class="btn btn--ghost">
                <i class="bi bi-x-lg"></i>
                Cancelar
            </a>

            <button type="submit" class="btn btn--primary">
                <i class="bi bi-floppy"></i>
                Salvar Alterações
            </button>
        </div>

    </div>

</form>

<form method="post" action="./deleteUsuario" id="form-excluir-usuario"
    onsubmit="return confirm('Deseja realmente excluir este usuário? Esta ação não pode ser desfeita.')">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $usuario['id_usuario'] ?>">
</form>