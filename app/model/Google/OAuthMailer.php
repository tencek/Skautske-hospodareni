<?php

declare(strict_types=1);

namespace Model\Google;

use Google\Service\Gmail;
use Nette\Mail\Mailer;
use Nette\Mail\Message;

use function array_key_exists;
use function base64_encode;
use function sprintf;
use function str_replace;

class OAuthMailer implements Mailer
{
    private Gmail $gmailService;

    public function __construct(GoogleService $googleService, OAuth $oAuth)
    {
        $client = $googleService->getClient();
        $token  = $client->fetchAccessTokenWithRefreshToken($oAuth->getToken());
        if (array_key_exists('error', $token)) {
            throw new InvalidOAuth(sprintf('%s => %s', $token['error'], $token['error_description']));
        }

        $client->setAccessToken($token);
        $this->gmailService = new Gmail($client);
    }

    public function send(Message $mail): void
    {
        $gmsg = new Gmail\Message();
        $gmsg->setRaw($this->urlSafeBase64Encode($mail->generateMessage()));
        $this->gmailService->users_messages->send('me', $gmsg);
    }

    private function urlSafeBase64Encode(string $msg): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($msg));
    }
}
