<?php

declare(strict_types=1);

namespace Sendportal\Base\Adapters;

use Illuminate\Support\Arr;
use Sendportal\Base\Services\Messages\MessageTrackingOptions;
use GuzzleHttp\Client;

class AzureAdapter extends BaseMailAdapter
{
    /**
     * @throws DomainException
     * @throws \Throwable
     */
    public function send(string $fromEmail, string $fromName, string $toEmail, string $subject, MessageTrackingOptions $trackingOptions, string $content): string
    {
        try {
            $requestBody = [
                'senderAddress'=> $fromEmail,
                'content'=> [
                    "subject"=> $subject,
                    "html"=> $content,
                ],
                "recipients"=>[
                    'to'=> [
                        [
                            "address"=> $toEmail,
                        ]
                    ]
                ]
            ];
    
            $uriPathQuery = '/emails:send?api-version=2023-03-31';
            $timestamp = gmdate('D, d M Y H:i:s T', time());
            $contentHash = $this->generateContentHash($requestBody);
            $host = parse_url(Arr::get($this->config, 'azure_host'), PHP_URL_HOST);
            $signature = $this->generateSignature(
                'POST', 
                $uriPathQuery, 
                $timestamp,
                $host, 
                $contentHash,
                Arr::get($this->config, 'key')
            );

            $client = new Client();
            $response = $client->post(Arr::get($this->config, 'azure_host') . $uriPathQuery, [
                'headers'=> [
                    'Content-Type' => 'application/json',
                    'x-ms-date'=> $timestamp,
                    'host'=> $host,
                    'x-ms-content-sha256'=> $contentHash,
                    'Authorization'=> "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature={$signature}"
                ],
                'json'=> $requestBody
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
    
            return $data['id'];
        } catch (\GuzzleHttp\Exception\ClientException $th) {
            throw new DomainException($th->getMessage());
        }

    }

    protected function generateSignature($httpMethod, $uriPathQuery, $timestamp, $host, $contentHash, $key): string
    {
        // https://learn.microsoft.com/en-us/rest/api/communication/authentication?view=rest-communication-callautomation-2023-06-15-preview#signing-an-http-request
        $stringToSign = "{$httpMethod}\n{$uriPathQuery}\n{$timestamp};{$host};{$contentHash}";
        return base64_encode(hash_hmac('sha256', mb_convert_encoding($stringToSign, 'UTF-8'), base64_decode($key), true));
    }

    protected function generateContentHash($requestBody): string
    {
        return base64_encode(hash('sha256', json_encode($requestBody), true));
    }

}
