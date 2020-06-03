<?php

?><!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Ethereum listener test client</title>
    <link rel="stylesheet" type="text/css" href="css/style.css" />
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
</head>
<body>
<ul class="pages">
    <li class="chat page">
        <div class="container-fluid">
        <div class="row">
            <div id="chat" class="col-sm-4">
                <div class="chatArea">
                    <ul class="messages"></ul>
                </div>
                <div class="chat-message-container">
                    <input class="inputMessage" placeholder="Type here..." />
                </div>
            </div>
            <div id="block_list" class="col-sm-4 table-scroll-container">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th scope="col">BLOCKS<br />Block Number / Hash</th>
                        <th scope="col" class="text-center">Tx Count</th>
                        <th scope="col" class="text-center">Gas Used<br />/ Limit</th>
                        <th scope="col" class="text-center">Timestamp</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div id="tx_list" class="col-sm-4 table-scroll-container">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th scope="col">TRANSACTIONS<br />Block№ - TxIndex / TxHash</th>
                        <th scope="col" class="text-center">Gas Value<br />/ Used*Price</th>
                        <th scope="col" class="text-center">Value</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </li>
    <li class="login page">
        <div class="form">
            <h3 class="title">What's your nickname?</h3>
            <input class="usernameInput" type="text" maxlength="14" />
        </div>
    </li>
</ul>

<script type="application/javascript" src="js/jquery.min.js"></script>
<script type="application/javascript" src="js/socket.io-client/socket.io.js"></script>
<script type="application/javascript" src="js/main.js"></script>
</body>
</html>
