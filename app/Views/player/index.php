<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>ChillQuiz Player</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/chillquizV3/public/assets/css/player.css">
</head>
<body>

<div id="app">

    <!-- HEADER -->
    <div class="header">
        <div id="player-display-name">Player</div>
        <div class="capitale">
            💰 <span id="capitale-value">0</span>
        </div>
    </div>

    <div class="content">

        <!-- ACCESSO -->
        <div id="screen-accesso" class="screen">
            <h1>ChillQuiz</h1>
            <input type="text" id="player-name" placeholder="Inserisci il tuo nome">
            <button id="btn-entra" class="btn-primary">Entra</button>
        </div>

        <!-- LOBBY -->
        <div id="screen-lobby" class="screen hidden">
            <h2>In attesa che inizi la partita...</h2>
        </div>

        <!-- PUNTATA -->
        <div id="screen-puntata" class="screen hidden">
            <h2>Fai la tua puntata</h2>
            <input type="number" id="puntata" placeholder="Importo">
            <button id="btn-punta" class="btn-primary">Punta</button>
        </div>

        <!-- DOMANDA -->
        <div id="screen-domanda" class="screen hidden">
            <h2 id="domanda-testo"></h2>
            <div id="opzioni" class="grid-opzioni"></div>
        </div>

        <!-- RISULTATI -->
        <div id="screen-risultati" class="screen hidden">
            <h2>Risultati</h2>

            <div id="risultato-personale" class="risultato-box"></div>

            <hr style="margin:20px 0;">

            <div id="classifica"></div>
        </div>

        <!-- FINE -->
        <div id="screen-fine" class="screen hidden">
            <h2>Partita conclusa</h2>
        </div>

    </div>

</div>

<script src="/chillquizV3/public/assets/js/player.js"></script>

</body>
</html>