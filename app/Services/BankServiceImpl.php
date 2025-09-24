<?php

namespace App\Services;

// use DataResponse;
use DataResponse;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class BankServiceImpl
{
    // Your service methods go here

    public function whitelistAccountToBank(string $payeeAccountNumber): object{
        // Prepare data to be encrypted

        $data_object = json_encode([
            'mc_id' => config('services.aba.merchantid'), // merchant_id
            'payee' => $payeeAccountNumber, // Meneficiary indentifier. It can be MID or Account
        ]);
        // RSA public key provided by the bank
        $path = storage_path('key/aba_rsa_public.pem');

        if (!file_exists($path)) {
            throw new Exception("Public key file not found at: {$path}");
        }

        $publicKey = file_get_contents($path);
        $publicKeyResource = openssl_pkey_get_public($publicKey);

        if ($publicKeyResource === false) {
            throw new Exception('Invalid public key: ' . openssl_error_string());
        }
        // Maximum length for encryption chunks
        $maxlength = 117;
        // Initialize output for encrypted data
        $encrypted_output = '';
        // Encrypt data in chunks
        while ($data_object !== '') {
            // Extract a substring of the allowed maximum length
            $chunk = substr($data_object, 0, $maxlength);
            $data_object = substr($data_object, $maxlength);
            // Encrypt the chunk using the public key
            if (openssl_public_encrypt($chunk, $encrypted_chunk, $publicKeyResource)) {
                $encrypted_output .= $encrypted_chunk;
            } else {
                // Handle encryption failure (optional: log the error or throw an exception)
                throw new Exception('Encryption failed for a data chunk.');
            }
        }
        $request_time = date('Ymdhis');
        // Encode the concatenated encrypted output in Base64
        $merchant_auth = base64_encode($encrypted_output);
        // public key provided by ABA Bank
        $api_key = config('services.aba.apikey');

        // Prepare the data to be hashed
        $b4hash = $request_time . $merchant_auth;

        // Generate the HMAC hash using SHA-512 and encode it in Base64
        $hash = base64_encode(hash_hmac('sha512', $b4hash, $api_key, true));
        $body = json_encode([
            'request_time' => date('Ymdhis'),
            'merchant_id' => config('services.aba.merchantid'),
            'merchant_auth' => $merchant_auth,
            'hash' => $hash
        ]);
        return $this->requestAddAccountToWhitelist($body);
    }

    private function requestAddAccountToWhitelist($body){
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json'
        ];
        $endpoint = $this->getAddWhitelistAccountEndpoint();

        try {
            $request = new Request('POST', $endpoint, $headers, $body);
            $res = $client->sendAsync($request)->wait();
            $statusCode = $res->getStatusCode();
            $content    = $res->getBody()->getContents();

            $contentDecoded = json_decode($content, true);
            // Log::info($contentDecoded);

            return DataResponse::JsonResult($contentDecoded,false);

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Network error, invalid response, etc.
            $log = json_decode($e->getResponse()->getBody(), true);
            // Log::info($log['response']);
            $code = null;

            // Try to decode response first
            if (!empty($log['status'])) {
                $responseData = $log['status'];
                if ($responseData && isset($responseData['message'])) {
                    $payeeMessage = $responseData['message'];
                    $code = $responseData['code'];
                } else {
                    $payeeMessage = null;
                }
            } else {
                $payeeMessage = null;
            }
            $statusCode = $e->getResponse()->getStatusCode();

            Log::error("ABA API Request Exception", [
                'status_code' => $statusCode,
                'code' => $code,
                // 'request' => (string) $e->getRequest()->getBody(),
                'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
            ]);
            $isAllow = $code == 'PTL148' ? false:true;
            return DataResponse::JsonResult(null,$isAllow,$payeeMessage,[],$isAllow ? 200:$statusCode);

        } catch (Exception $e) {
            // Any other PHP error
            Log::error("ABA API General Error", [
                'message' => $e->getMessage(),
            ]);

            return DataResponse::Error('Unexpected error occurred');
        }
    }

    private function getUpdateWhitelistAccountStatusEndpoint():string{
        return 'https://checkout-sandbox.payway.com.kh/api/merchant-portal/merchant-access/whitelist-account/update-whitelist-status';
    }
    private function getAddWhitelistAccountEndpoint():string{
        return 'https://checkout-sandbox.payway.com.kh/api/merchant-portal/merchant-access/whitelist-account/add-whitelist-payout';
    }

    private function requestUpdateAccountToWhitelistStatus($body){
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json'
        ];
        $endpoint = $this->getUpdateWhitelistAccountStatusEndpoint();

        try {
            $request = new Request('POST', $endpoint, $headers, $body);
            $res = $client->sendAsync($request)->wait();
            $statusCode = $res->getStatusCode();
            $content    = $res->getBody()->getContents();

            // Log::info("ABA API Response", [
            //     'status'  => $statusCode,
            //     'content' => $content
            // ]);

            $contentDecoded = json_decode($content, true);
            Log::info($contentDecoded['content']['currency']);

            return DataResponse::JsonResult($contentDecoded,false,'update');

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Network error, invalid response, etc.
            $log = json_decode($e->getResponse()->getBody(), true);
            // Log::info($log['response']);

            // Try to decode response first
            if (!empty($log['status'])) {
                $responseData = $log['status'];
                if ($responseData && isset($responseData['message'])) {
                    $payeeMessage = $responseData['message'];
                } else {
                    $payeeMessage = null;
                }
            } else {
                $payeeMessage = null;
            }

            Log::error("ABA API Request Exception", [
                // 'status_message' => $payeeMessage,
                // 'request' => (string) $e->getRequest()->getBody(),
                'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
            ]);

            return DataResponse::Error($payeeMessage);

        } catch (Exception $e) {
            // Any other PHP error
            Log::error("ABA API General Error", [
                'message' => $e->getMessage(),
            ]);

            return DataResponse::Error('Unexpected error occurred');
        }
    }

}
