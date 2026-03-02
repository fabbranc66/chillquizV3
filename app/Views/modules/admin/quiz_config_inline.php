<?php /*
 * FILE: app/Views/modules/admin/quiz_config_inline.php
 * RUOLO: Modulo configurazione quiz inline nella regia admin.
 * UTILIZZATO DA: app/Views/admin/index.php
 * ELEMENTI USATI DA JS: #quiz-config-form e campi child.
 */ ?>
<div class="module-debug-tag">admin/quiz_config_inline.php</div>
<div class="quiz-config-wrap">
    <h3>⚙️ Configurazione Quiz</h3>

    <form id="quiz-config-form" class="quiz-config-form">
        <div class="quiz-grid">
            <label>
                Nome quiz
                <input type="text" id="qc-nome" name="nome_quiz" placeholder="es. quiz_storia_1" required>
            </label>

            <label>
                Numero domande
                <input type="number" id="qc-numero" name="numero_domande" min="1" max="100" value="10" required>
            </label>

            <label>
                Modalità
                <select id="qc-modalita" name="modalita">
                    <option value="manuale">Manuale (argomento specifico)</option>
                    <option value="mista">Misto (tutti gli argomenti)</option>
                </select>
            </label>

            <label id="qc-argomento-wrap">
                Argomento
                <select id="qc-argomento" name="argomento_id">
                    <option value="">Seleziona argomento</option>
                    <?php foreach (($argomenti ?? []) as $argomento): ?>
                        <option value="<?= (int) $argomento['id'] ?>"><?= htmlspecialchars((string) $argomento['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <p class="quiz-config-note">
            Selezione domande: <strong>automatica</strong>.
            In manuale filtra per argomento; in misto pesca da tutte le domande.
        </p>

        <div class="quiz-config-actions">
            <button type="submit" id="btnSalvaQuizConfig">Salva Configurazione</button>
            <button type="button" id="btnNuovaConConfig">Nuova Sessione con questa config</button>
        </div>
    </form>
</div>

