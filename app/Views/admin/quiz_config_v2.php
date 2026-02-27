<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz V3 - Quiz Config V2</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/chillquizV3/public/assets/css/admin.css">
    <style>
        :root {
            --k-purple: #46178f;
            --k-blue: #1368ce;
            --k-cyan: #0aa6ff;
            --k-green: #0f9d58;
            --k-yellow: #f7b500;
            --k-red: #e21b3c;
            --k-pink: #ff4fbe;
            --k-panel: #1f0b4f;
            --k-panel-2: #26105f;
            --k-text: #ffffff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--k-text);
            font-family: "Segoe UI", "Inter", system-ui, sans-serif;
            background:
                radial-gradient(circle at 12% 15%, rgba(16, 160, 255, 0.32), transparent 38%),
                radial-gradient(circle at 82% 8%, rgba(255, 79, 190, 0.26), transparent 32%),
                radial-gradient(circle at 80% 85%, rgba(15, 157, 88, 0.2), transparent 36%),
                linear-gradient(145deg, #2f0b76 0%, #1b0553 48%, #120239 100%);
        }

        .container {
            max-width: 1220px;
            margin: 0 auto;
            padding: 18px 16px 26px;
        }

        .top-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .top-title h2 {
            margin: 0;
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 900;
            letter-spacing: .4px;
            text-shadow: 0 4px 20px rgba(0, 0, 0, .3);
        }

        .back-link {
            color: #89fff3;
            font-weight: 700;
            text-decoration: none;
        }

        .back-link:hover { text-decoration: underline; }

        .card {
            margin: 14px auto 0;
            text-align: left;
            background: linear-gradient(175deg, rgba(62, 20, 152, .95), rgba(33, 11, 91, .95));
            border: 2px solid rgba(255, 255, 255, 0.28);
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 14px 35px rgba(0, 0, 0, .38);
        }

        .card h3 {
            margin: 0 0 4px;
            font-size: clamp(22px, 2.4vw, 30px);
            font-weight: 800;
        }

        .subtitle {
            margin: 0 0 16px;
            opacity: .92;
            font-size: 14px;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        button {
            border: 0;
            border-radius: 12px;
            padding: 11px 14px;
            cursor: pointer;
            color: #fff;
            font-weight: 800;
            transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
            box-shadow: 0 8px 18px rgba(0, 0, 0, .28);
        }

        button:hover {
            transform: translateY(-1px) scale(1.012);
            filter: saturate(1.08);
            box-shadow: 0 10px 20px rgba(0, 0, 0, .34);
        }

        #btn-schema-init { background: linear-gradient(130deg, var(--k-blue), var(--k-cyan)); }
        #btn-list { background: linear-gradient(130deg, var(--k-green), #2ac66f); }
        #btn-save { background: linear-gradient(130deg, var(--k-red), #ff5f2a); width: 100%; margin-top: 6px; }
        #btn-load-into-form { background: linear-gradient(130deg, #6f42ff, #3bb8ff); width: 100%; margin-top: 8px; }
        #btn-get { background: linear-gradient(130deg, #8c33ff, var(--k-pink)); width: 100%; }
        #btn-load-questions { background: linear-gradient(130deg, #1ba2ff, #4ad1ff); }
        #btn-sync-csv { background: linear-gradient(130deg, var(--k-yellow), #ff8a00); color: #231300; }
        #btn-genera-domande { background: linear-gradient(130deg, #22c55e, #14b8a6); }
        #btn-save-domande { background: linear-gradient(130deg, #f97316, #ef4444); }

        .grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
        }

        .panel {
            background: linear-gradient(170deg, rgba(30, 12, 77, .94), rgba(21, 8, 58, .92));
            border: 2px solid rgba(255, 255, 255, .18);
            border-radius: 16px;
            padding: 14px;
        }

        .panel h4 {
            margin: 0 0 10px;
            color: #ffe56d;
            font-size: 18px;
            font-weight: 800;
            text-shadow: 0 2px 8px rgba(0, 0, 0, .45);
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 10px;
        }

        .field label {
            font-size: 13px;
            opacity: .95;
            font-weight: 700;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            background: rgba(8, 6, 29, .9);
            color: #fff;
            border: 2px solid rgba(151, 160, 255, .6);
            border-radius: 10px;
            padding: 8px;
            font-size: 14px;
            outline: none;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: #62fff0;
            box-shadow: 0 0 0 2px rgba(98, 255, 240, .2);
        }

        .field textarea {
            min-height: 82px;
            resize: vertical;
        }

        .result {
            margin-top: 14px;
            background: rgba(7, 8, 28, .95);
            border: 2px solid rgba(80, 255, 236, .4);
            border-radius: 10px;
            padding: 10px;
            min-height: 120px;
            max-height: 360px;
            overflow: auto;
            white-space: pre-wrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
        }

        .hint {
            font-size: 13px;
            opacity: .92;
            margin-top: 8px;
        }

        .question-tools {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .question-tools #question-search {
            flex: 1 1 220px;
        }

        .question-list {
            border: 2px solid rgba(255, 255, 255, .2);
            border-radius: 12px;
            background: rgba(7, 8, 28, .88);
            max-height: 280px;
            overflow: auto;
            padding: 8px;
            margin-bottom: 10px;
        }

        .question-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 7px 6px;
            border-bottom: 1px dashed rgba(255, 255, 255, .22);
            font-size: 13px;
        }

        .question-item:last-child { border-bottom: none; }

        .question-meta {
            opacity: .86;
            font-size: 12px;
        }

        .question-item input {
            margin-top: 3px;
            transform: scale(1.15);
            accent-color: #00ffd5;
        }
    </style>
</head>
<?php $adminToken = getenv('ADMIN_TOKEN') ?: 'SUPERSEGRETO123'; ?>
<body class="<?= !empty($showModuleTags) ? 'module-tags-on' : 'module-tags-off' ?>">
<div class="container">
    <div class="top-title">
        <h2>🎮 Quiz Config V2</h2>
        <a href="index.php?url=admin/index" class="back-link">← Torna alla regia</a>
    </div>

    <div class="card">
        <h3>Gestione quiz in stile Kahoot ⚡</h3>
        <p class="subtitle">Visuale vivace e rapida per creare, aggiornare e testare configurazioni quiz.</p>

        <div class="toolbar">
            <button id="btn-schema-init" type="button">1) Schema Init (POST)</button>
            <button id="btn-list" type="button">2) Lista Configurazioni</button>
        </div>

        <div class="grid">
            <section class="panel">
                <h4>Crea / Aggiorna Configurazione</h4>

                <div class="field">
                    <label for="cfg-id">ID (vuoto = nuova)</label>
                    <input id="cfg-id" type="number" min="1" placeholder="es. 3">
                    <button id="btn-load-into-form" type="button">Carica ID nel form per modifica</button>
                </div>

                <div class="field">
                    <label for="cfg-nome">nome_quiz *</label>
                    <input id="cfg-nome" type="text" placeholder="es. quiz_sport_sera">
                </div>

                <div class="field">
                    <label for="cfg-titolo">titolo *</label>
                    <input id="cfg-titolo" type="text" placeholder="es. Quiz Sport Sera">
                </div>

                <div class="field">
                    <label for="cfg-modalita">modalita *</label>
                    <select id="cfg-modalita">
                        <option value="mista">Mista</option>
                        <option value="auto">Auto</option>
                        <option value="manuale">Manuale</option>
                    </select>
                </div>

                <div class="field">
                    <label for="cfg-numero">numero_domande *</label>
                    <input id="cfg-numero" type="number" min="1" value="10">
                </div>

                <div class="field">
                    <label for="cfg-argomento">Argomento (ID)</label>
                    <input id="cfg-argomento" type="number" min="0" placeholder="0 = nessun argomento (nessun filtro)">
                </div>

                <div class="field">
                    <label for="cfg-selezione">selezione_tipo *</label>
                    <select id="cfg-selezione">
                        <option value="auto">Auto</option>
                        <option value="manuale">Manuale</option>
                    </select>
                </div>

                <div class="field">
                    <label for="cfg-attiva">attiva *</label>
                    <select id="cfg-attiva">
                        <option value="1">1</option>
                        <option value="0">0</option>
                    </select>
                </div>

                <div class="field">
                    <label for="cfg-domande">Domande manuali (ID separati da virgola, es: 3,8,11)</label>
                    <textarea id="cfg-domande" placeholder="usato in modalità &quot;manuale&quot; con selezione &quot;manuale&quot;"></textarea>
                </div>

                <button id="btn-save" type="button">3) Salva Configurazione</button>

                <p class="hint">
                    <strong>Guida rapida:</strong><br>
                    • <strong>Modalità mista</strong>: argomento_id deve essere 0 (nessun filtro).<br>
                    • <strong>Modalità auto</strong>: argomento impostato dal sistema, ma modificabile da admin.<br>
                    • <strong>Selezione tipo auto</strong>: il sistema genera domande in base all'argomento.<br>
                    • <strong>Selezione tipo manuale</strong>: admin sceglie la lista domande e poi la salva.
                </p>
                <p class="hint">Se metti ID aggiorna. Se ID è vuoto crea nuova.</p>
            </section>

            <section class="panel">
                <h4>Dettaglio configurazione</h4>
                <div class="field">
                    <label for="get-id">ID configurazione</label>
                    <input id="get-id" type="number" min="1" placeholder="es. 1">
                </div>
                <button id="btn-get" type="button">4) Carica dettaglio</button>


                <h4 style="margin-top:18px;">Seleziona domande da elenco</h4>
                <div class="question-tools">
                    <input id="question-search" type="text" placeholder="Cerca testo domanda...">
                    <button id="btn-load-questions" type="button">Carica domande</button>
                    <button id="btn-sync-csv" type="button">Usa selezione → CSV</button>
                    <button id="btn-genera-domande" type="button">Genera domande</button>
                    <button id="btn-save-domande" type="button">Salva domande in tabella</button>
                </div>
                <div id="question-list" class="question-list"></div>

                <h4 style="margin-top:18px;">Output API</h4>
                <div id="api-output" class="result"></div>
            </section>
        </div>

        <p class="hint">
            Questa pagina usa direttamente gli endpoint `api-v2` con token admin incluso in query string per ambienti hosting (es. Aruba), senza dipendere da tool esterni.
        </p>
    </div>
</div>

<script>
(() => {
    const adminToken = <?= json_encode($adminToken, JSON_UNESCAPED_UNICODE) ?>;

    const basePath = `${window.location.pathname.replace(/index\.php$/, '')}index.php`;

    const buildApiUrl = (action, extra = {}) => {
        const params = new URLSearchParams({
            url: `api-v2/${action}`,
            admin_token: adminToken,
            ...extra
        });

        return `${basePath}?${params.toString()}`;
    };

    const out = document.getElementById('api-output');
    const show = (obj) => {
        out.textContent = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2);
    };

    document.getElementById('btn-schema-init').onclick = async () => {
        const res = await fetch(buildApiUrl('schema-init'), { method: 'POST' });
        show(await res.json());
    };

    document.getElementById('btn-list').onclick = async () => {
        const res = await fetch(buildApiUrl('list'));
        show(await res.json());
    };

    const setFormFromConfiguration = (config) => {
        document.getElementById('cfg-id').value = config.id || '';
        document.getElementById('cfg-nome').value = config.nome_quiz || '';
        document.getElementById('cfg-titolo').value = config.titolo || '';
        document.getElementById('cfg-modalita').value = config.modalita || 'mista';
        document.getElementById('cfg-numero').value = Number(config.numero_domande || 10);
        document.getElementById('cfg-argomento').value = Number(config.argomento_id || 0);
        document.getElementById('cfg-selezione').value = config.selezione_tipo || 'auto';
        document.getElementById('cfg-attiva').value = Number(config.attiva || 0) === 1 ? '1' : '0';

        const domandeManuali = Array.isArray(config.domande_manuali)
            ? config.domande_manuali.map((x) => Number(x.domanda_id || x)).filter((v) => Number.isInteger(v) && v > 0)
            : [];
        document.getElementById('cfg-domande').value = domandeManuali.join(',');
    };

    const loadConfigurationById = async (id) => {
        if (!Number.isInteger(id) || id <= 0) {
            show({ success: false, error: 'Inserisci un ID valido' });
            return;
        }

        const res = await fetch(buildApiUrl('get', { id: String(id) }));
        const data = await res.json();
        show(data);

        if (!data || !data.success || !data.configurazione) {
            return;
        }

        setFormFromConfiguration(data.configurazione);
        document.getElementById('get-id').value = String(id);

        show({
            ...data,
            note: 'Configurazione caricata nel form di sinistra: ora puoi modificarla e premere Salva.'
        });
    };

    document.getElementById('btn-get').onclick = async () => {
        const id = Number(document.getElementById('get-id').value || 0);
        await loadConfigurationById(id);
    };

    document.getElementById('btn-load-into-form').onclick = async () => {
        const id = Number(document.getElementById('cfg-id').value || 0);
        await loadConfigurationById(id);
    };

    document.getElementById('cfg-id').addEventListener('change', async () => {
        const id = Number(document.getElementById('cfg-id').value || 0);
        if (id > 0) {
            await loadConfigurationById(id);
        }
    });

    document.getElementById('btn-save').onclick = async () => {
        const payload = {
            id: document.getElementById('cfg-id').value ? Number(document.getElementById('cfg-id').value) : null,
            nome_quiz: document.getElementById('cfg-nome').value.trim(),
            titolo: document.getElementById('cfg-titolo').value.trim(),
            modalita: document.getElementById('cfg-modalita').value,
            numero_domande: Number(document.getElementById('cfg-numero').value || 10),
            argomento_id: document.getElementById('cfg-argomento').value ? Number(document.getElementById('cfg-argomento').value) : 0,
            selezione_tipo: document.getElementById('cfg-selezione').value,
            attiva: Number(document.getElementById('cfg-attiva').value || 1),
            domande_manuali: document.getElementById('cfg-domande').value.trim(),
            admin_token: adminToken
        };

        const body = new URLSearchParams();
        Object.entries(payload).forEach(([k, v]) => {
            if (v !== null && v !== undefined) {
                body.append(k, String(v));
            }
        });

        const res = await fetch(buildApiUrl('save'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body
        });

        const data = await res.json();
        show(data);

        if (data && data.success) {
            document.getElementById('cfg-id').value = data.id;
        }
    };


    const questionListEl = document.getElementById('question-list');

    const parseCsvIds = () => {
        const raw = document.getElementById('cfg-domande').value.trim();
        if (!raw) return [];
        return raw.split(',').map((v) => Number(v.trim())).filter((v) => Number.isInteger(v) && v > 0);
    };

    const renderQuestions = (items) => {
        const selected = new Set(parseCsvIds());

        if (!Array.isArray(items) || items.length === 0) {
            questionListEl.innerHTML = '<div class="question-item">Nessuna domanda trovata</div>';
            return;
        }

        questionListEl.innerHTML = items.map((q) => {
            const checked = selected.has(Number(q.id)) ? 'checked' : '';
            const testo = String(q.testo || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const argomento = String(q.argomento_nome || '-').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            return `
                <label class="question-item">
                    <input type="checkbox" class="question-check" value="${q.id}" ${checked}>
                    <div>
                        <div><strong>#${q.id}</strong> ${testo}</div>
                        <div class="question-meta">Argomento: ${argomento}</div>
                    </div>
                </label>
            `;
        }).join('');
    };

    document.getElementById('btn-load-questions').onclick = async () => {
        const argomento = document.getElementById('cfg-argomento').value.trim();
        const q = document.getElementById('question-search').value.trim();

        const extra = {};
        if (argomento !== '' && Number(argomento) > 0) {
            extra.argomento_id = argomento;
        }
        if (q !== '') {
            extra.q = q;
        }

        const res = await fetch(buildApiUrl('domande', extra));
        const data = await res.json();
        show(data);
        renderQuestions(data.domande || []);
    };

    document.getElementById('btn-sync-csv').onclick = () => {
        const checks = Array.from(document.querySelectorAll('.question-check:checked'));
        const ids = checks.map((el) => Number(el.value)).filter((v) => Number.isInteger(v) && v > 0);
        document.getElementById('cfg-domande').value = ids.join(',');
        show({ success: true, domande_manuali: ids });
    };

    document.getElementById('btn-genera-domande').onclick = async () => {
        const payload = {
            id: document.getElementById('cfg-id').value ? Number(document.getElementById('cfg-id').value) : null,
            nome_quiz: document.getElementById('cfg-nome').value.trim() || 'preview',
            titolo: document.getElementById('cfg-titolo').value.trim() || 'Preview',
            modalita: document.getElementById('cfg-modalita').value,
            numero_domande: Number(document.getElementById('cfg-numero').value || 10),
            argomento_id: document.getElementById('cfg-argomento').value ? Number(document.getElementById('cfg-argomento').value) : 0,
            selezione_tipo: document.getElementById('cfg-selezione').value,
            attiva: Number(document.getElementById('cfg-attiva').value || 1),
            domande_manuali: document.getElementById('cfg-domande').value.trim(),
            admin_token: adminToken
        };

        const body = new URLSearchParams();
        Object.entries(payload).forEach(([k, v]) => body.append(k, String(v)));

        const res = await fetch(buildApiUrl('generate-domande'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body
        });

        const data = await res.json();
        show(data);
        renderQuestions(data.domande || []);

        if (Array.isArray(data.domande) && data.domande.length > 0) {
            const ids = data.domande.map((d) => Number(d.id)).filter((v) => Number.isInteger(v) && v > 0);
            document.getElementById('cfg-domande').value = ids.join(',');
        }
    };

    document.getElementById('btn-save-domande').onclick = async () => {
        const id = Number(document.getElementById('cfg-id').value || 0);
        if (id <= 0) {
            show({ success: false, error: 'Salva prima la configurazione per ottenere un ID' });
            return;
        }

        const body = new URLSearchParams();
        body.append('id', String(id));
        body.append('domande', document.getElementById('cfg-domande').value.trim());

        const res = await fetch(buildApiUrl('save-domande'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body
        });

        const data = await res.json();
        show(data);
    };

    show('Pronto. 1) schema-init 2) list 3) save 4) get. Usa anche Carica domande per selezione manuale.');
})();
</script>
</body>
</html>
