<?php
session_start();
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Simple workflow</title>
        <script type='text/javascript'>
            function useEtalon() {
                if (!window['ethereum']) {
                    document.getElementById("result").textContent = "No Web3 compatible wallet is found.";
                    return;
                }
                getSignedMessage().then((msg_sig) => {
                    fetch("testSignature.php", {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(msg_sig)})
                        .then(response => response.json())
                        .then(result => {
                            console.log("debug wallet address:", result['address']);
                            document.getElementById("result").textContent = (result['attribution']? 'Etalon attribution validated!' : 'Wallet does not hold at least one Etalon.');
                        })
                        .catch(e => console.error(e));
                })
                .catch(e => console.error(e));
            }
            
            function getSignedMessage() {
                return new Promise((resolve, reject) => {
                    fetch("newMessage.php")
                        .then(response => response.json())
                        .then(result => {
                            signMessageWithWallet(result['message']).then((signature) => {
                                if (!signature) {
                                    reject('signing failed');
                                } else {
                                    resolve({message: result['message'], signature: signature});
                                }
                            });
                        })
                        .catch(e => reject(e));
                });
            }
            
            function signMessageWithWallet(message) {
                return window['ethereum'].request({ method: 'eth_requestAccounts' })
                    .then((accounts) => {
                        // TODO: The user may select multiple accounts... that would be bad practise..
                        // as they only need a single one to connect here that has Etalon... so we use
                        // the first one here only, but each could be tried until one succeeds or neither does.
                        const selectedAccount = accounts[0];
                        return window['ethereum'].request({
                            method: 'personal_sign',
                            params: [message, selectedAccount],
                        });
                    }).catch ((e) => {
                        if (e.code === 4001) {
                            document.getElementById("result").textContent = 'You rejected the account request.';
                        } else {
                            document.getElementById("result").textContent = 'Could not connect to crypto-wallet. Make sure wallet is compatible and set up.';
                        }
                    });
            }
        </script>
    </head>
    <body>
        <p>Let's imagine now that the user is at the point where Etalon attribution is relevant, checkout in a web-shop for example.</p>
        <p>There should be a "Use Etalon" button or banner near a place where a cupon code is generally present. The client may check if there's a crypto-wallet available and then remind the user to click it if they have Etalon.</p>
        <p>The user starts the process by clicking "Use Etalon":</p>
        <ol>
            <li>The client in the browser shall ask the server for a secure message to sign for wallet-ownership proof.
            <li>The client shall connect to the wallet accessible in the browser and ask for a signature of the message. (the user needs to approve both operations)
            <li>The client shall send the message and the signature back to the server for evaluation.
            <li>The client shall display if the wallet was identified successfully and it holds sufficient Etalon tokens.
            <li>Allow the user to retry the process, maybe they've selected the wrong account in the beginning in their wallet.
        </ol>
        <button onclick="useEtalon()">Use Etalon</button>
        <p id="result"></p>
    </body>
</html>
