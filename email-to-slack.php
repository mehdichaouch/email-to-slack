<?php
/**
 * Email to Slack
 *
 * @author Mehdi Chaouch (@MehdiChch)
 * @license MIT
 * @see https://github.com/mehdichaouch/email-to-slack
 */

function validate()
{
    $json = json_decode(file_get_contents('php://input'), true);

    if ('message' != $json['event']['type']) {
        http_response_code(422); // event type must be `message`
        exit();
    }

    $appId = ($json['api_app_id'] === $_ENV['APP_ID']);
    $token = ($json['token'] === $_ENV['VERIFICATION_TOKEN']);
    $teamId = ($json['team_id'] === $_ENV['TEAM_ID']);
    $channel = (isset($json['event']['channel']) && $json['event']['channel'] === $_ENV['USLACKBOT_CHANNEL']);
    $user = (isset($json['event']['user']) && 'USLACKBOT' === $json['event']['user']);
    $subtype = (isset($json['event']['subtype']) && 'file_share' === $json['event']['subtype']);

    $error = '';
    if ($appId && $token && $teamId && $channel && $user && $subtype) {
        return true;
    } elseif (!$appId) {
        $error = 'APP_ID is not right!';
    } elseif (!$token) {
        $error = 'TOKEN is not right!';
    } elseif (!$teamId) {
        $error = 'TEAM_ID is not right!';
    } elseif (!$channel) {
        $error = 'USLACKBOT channel is not right!';
    } else if (in_array('X-Slack-Retry-Num', $_SERVER)) {
        http_response_code(409);
        exit('Duplicate');
    }

    http_response_code(400);
    exit($error);
}

function main()
{
    if ('GET' === $_SERVER['REQUEST_METHOD']) {
        header('Location: //github.com/mehdichaouch/email-to-slack', true, 303); // RTFM
        exit();
    }

    if ('POST' === $_SERVER['REQUEST_METHOD']) {
        $json = json_decode(file_get_contents('php://input'), true);

        if (in_array('challenge', array_keys($json))) {
            header('Content-Type: text/plain');
            exit($json['challenge']);
        }

        if (validate()) {
            $email = $json['event']['files'][0];

            $allTo = [];
            foreach ($email['to'] as $emailTo) {
                $allTo[] = $emailTo['original'];
            }

            $data = [
                'text' => '',
                'attachments' => [
                    [
                        'fallback' => 'An email was sent by ' . $email['from'][0]['original'],
                        'color' => '#d32600',
                        'pretext' => '',
                        'author_name' => $email['from'][0]['original'],
                        'author_link' => 'http://gmail.com/',
                        'author_icon' => '',
                        'title' => $email['title'],
                        'title_link' => 'http://gmail.com/',
                        'text' => $email['plain_text'],
                        'fields' => [],
                        'footer' => 'Sent to : ' . implode(', ', $allTo),
                        'footer_icon' => '',
                        'ts' => $email['timestamp'],
                    ]
                ]
            ];

            $allCc = [];
            foreach ($email['cc'] as $emailCc) {
                $allCc[] = $emailCc['original'];
            }
            if ($allCc = implode(', ', $allCc)) {
                $data['attachments'][0]['fields'][] = ['title' => 'cc', 'value' => $allCc];
            }

            if ($email['attachments']) {
                $data['attachments'][0]['fields'][] = [
                    'title' => '',
                    'value' => 'This email also has attachments',
                    'short' => false,
                ];
            }

            $curl = curl_init();
            curl_setopt_array(
                $curl,
                [
                    CURLOPT_URL => $_ENV['INCOMING_WEBHOOK_URL'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                    ],
                ]
            );
            $response = curl_exec($curl);
            curl_close($curl);

            http_response_code(200);
            exit('ok');
        } else {
            http_response_code(401);
            exit('Bad request');
        }
    }
}

main();
