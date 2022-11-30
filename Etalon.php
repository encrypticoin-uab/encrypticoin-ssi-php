<?php
/**
 *  Copyright (C) 2022 Encrypticoin UAB
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *  
 *      http://www.apache.org/licenses/LICENSE-2.0
 *  
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 * @author    Encrypticoin UAB Development <dev@etalon.cash>
 * @copyright 2022 Encrypticoin UAB
 * @license   https://apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link      https://etalon.cash/tia/docs
 */

/**
 * Explicit exception raised from the integration client.
 */
class IntegrationException extends Exception {}

/**
 * Raised when rate-limiting is triggered on the API server.
 */
class BackoffException extends IntegrationException {}

/**
 * Indicates client error or invalid signature.
 */
class SignatureValidationException extends IntegrationException {}

/**
 * Utility class to handle the token balance of an address.
 */
class TokenBalance
{
    public $address;
    public $balance;
    public $decimals;
    
    /**
     * Constructor
     * 
     * @param string $address
     * @param string $balance
     * @param int $decimals
     */
    public function __construct($address, $balance, $decimals)
    {
        $this->address = $address;
        $this->balance = $balance;
        $this->decimals = $decimals;
    }
    
    /**
     * Whether the wallet has enough tokens for attribution or not.
     * 
     * @return boolean
     */
    public function hasAttribution()
    {
        return (bool)$this->asInteger();
    }
    
    /**
     * Balance of "whole" tokens.
     * 
     * @return int
     */
    public function asInteger()
    {
        return (int)substr($this->balance, 0, strlen($this->balance)-$this->decimals);
    }
}

class TokenBalanceChange extends TokenBalance
{
    public $id;
    
    public function __construct($id, $address, $balance, $decimals)
    {
        parent::__construct($address, $balance, $decimals);
        $this->id = $id;
    }
}

/**
 * Lightweight client to the integration REST API.
 */
class ServerIntegrationClient
{
    public $urlBase = '';
    
    /**
     * Constructor
     * 
     * @param string $domain default value should be fine
     * @param string $apiPath default value should be fine
     */
    public function __construct($domain = 'etalon.cash', $apiPath = '/tia')
    {
        $this->urlBase = "https://{$domain}{$apiPath}";
    }
    
    /**
     * Query the API server for the validation and recovery of the 
     * crypto-wallet address that has signed the message.
     * The recovered address (if successfully retrieved) is in checksum format.
     * 
     * @param string $message
     * @param string $signature
     * @return string
     * @throws BackoffException
     * @throws SignatureValidationError
     * @throws IntegrationError
     */
    public function walletBySigned($message, $signature) {
        $result = $this->apiCall(
            $this->urlBase.'/wallet-by-signed',
            array('message' => $message, 'signature' => $signature)
        );
        if ($result['status'] === 429) {
            throw new BackoffException();
        }
        elseif ($result['status'] === 400) {
            throw new SignatureValidationError();
        }
        elseif ($result['status'] !== 200 || $result['data'] === null || !$result['data']['address']) {
            throw new IntegrationError();
        }
        return $result['data']['address'];
    }
    
    /**
     * Get the balance of tokens in the crypto-wallet by address.
     * The address value is case-sensitive, it must be in proper checksum format.
     * 
     * @param string $address
     * @return \TokenBalance
     * @throws BackoffException
     * @throws IntegrationError
     */
    public function tokenBalance($address) {
        $result = $this->apiCall(
            $this->urlBase.'/token-balance',
            array('address' => $address)
        );
        if ($result['status'] === 429) {
            throw new BackoffException();
        }
        elseif ($result['status'] !== 200 || $result['data'] === null) {
            throw new IntegrationError();
        }
        return new TokenBalance($address, $result['data']['balance'], $result['data']['decimals']);
    }
    
    /**
     * Get the token balance changes from the `since` number.
     * Call this periodically (every 10-20 seconds) to get the changes incrementally.
     * The next query shall be made with `changes[-1].id + 1`, or repeated with `since` if no changes were retrieved.
     * 
     * @param int $since
     * @return array
     * @throws BackoffException
     * @throws IntegrationError
     */
    public function tokenChanges($since) {
        $result = $this->apiCall(
            $this->urlBase.'/token-changes',
            array('since' => $since)
        );
        if ($result['status'] === 429) {
            throw new BackoffException();
        }
        elseif ($result['status'] !== 200 || $result['data'] === null) {
            throw new IntegrationError();
        }
        $changes = array();
        $decimals = $result['data']['decimals'];
        foreach ($result['data']['changes'] as $change) {
            array_push($changes, new TokenBalanceChange($change['id'], $change['address'], $change['balance'], $decimals));
        }
        return $changes;
    }

    /**
     * Get some info about the contract.
     * The returned keys are currently `contract_address`, `block_number` and `decimals`.
     * 
     * @return array
     * @throws BackoffException
     * @throws IntegrationError
     */
    public function contractInfo() {
        $result = $this->apiCall($this->urlBase.'/contract-info');
        if ($result['status'] === 429) {
            throw new BackoffException();
        }
        elseif ($result['status'] !== 200 || $result['data'] === null) {
            throw new IntegrationError();
        }
        return $result['data'];
    }
    
    /**
     * Call an API endpoint.
     * 
     * @param string $url
     * @param array $payload
     * @return array
     * @throws IntegrationException
     */
    protected function apiCall($url, $payload = null) {
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        if ($payload !== null) {
            curl_setopt($curlHandle, CURLOPT_POST, true);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        }
        $response = curl_exec($curlHandle);
        if (curl_errno($curlHandle)) {
            throw new IntegrationException(curl_error($curlHandle));
        }
        $result = array(
            'status' => curl_getinfo($curlHandle, CURLINFO_HTTP_CODE),
            'response' => $response,
            'data' => json_decode($response, true),
        );
        curl_close($curlHandle);
        return $result;
    }
}

/**
 * Simple crypto-wallet ownership message body creation utility.
 * The message to be signed by the client is actually arbitrary, but this is the baseline recommendation for:
 *  - Having a short human-readable description for transparency.
 *  - Including an arbitrary identifier managed by the server.
 */
class ProofMessageFactory
{
    public $description;
    
    /**
     * Description should be a concise explanation for the signature request. For example:
     *  - Wallet ownership proof for token attribution at XY web-shop.
     *  - Wallet ownership proof for token attribution by linking to account at XY web-shop.
     * 
     * @param string $description
     */
    public function __construct($description)
    {
        $this->description = $description;
    }
    
    /**
     * Use this to produce a message to be sent to the service-client.
     * The `message_id` must be secure against multiple use and unauthorized use. To this end, it should be
     * a secure random value bound to the session of the user.
     * For more information see the "Integration requirements" of `/wallet-by-signed` for more info.
     * 
     * @param string $messageId
     * @return string
     */
    public function create($messageId)
    {
        return "{$this->description}\nId: {$messageId}";
    }
    
    /**
     * Try to recover the `messageId` from a message.
     * 
     * @param string $maybeMessage
     * @return string or null
     */
    public function extractId($maybeMessage)
    {
        $idPrefix = "{$this->description}\nId: ";
        if (strlen($maybeMessage) > strlen($idPrefix) && strcmp(substr($maybeMessage, 0, strlen($idPrefix)), $idPrefix) === 0) {
            return substr($maybeMessage, strlen($idPrefix));
        }
        return null;
    }
}

