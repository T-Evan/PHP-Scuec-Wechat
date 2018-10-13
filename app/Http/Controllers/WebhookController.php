<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private $BASE_PATH = '';

    private $LOG_FILE = '';

    const REPOSITORY_FULL_NAME  = 'zixunminda/iscuecer';
    const REPOSITORY_UUID       = '{b6f9ee1a-f707-4be6-89b4-5c4da9216d4a}';
    const DEPLOY_KEYWORD        = 'DEPLOY_NOW';

    public function __construct()
    {
        $this->BASE_PATH = base_path();
        $this->LOG_FILE = storage_path('logs/webhook.log');
    }

    public function handler(Request $request)
    {
        if ($this->checkHeader('X-Event-Key', 'repo:push')
            && $this->checkIP($request->getClientIp()) ) {
            $jsonData = $request->input();
            //check repository
            if (
                isset($jsonData['repository']['full_name'])
                && isset($jsonData['repository']['uuid'])
                && self::REPOSITORY_FULL_NAME === $jsonData['repository']['full_name']
                && self::REPOSITORY_UUID === $jsonData['repository']['uuid']
            ) {
                // check the push message. The Message that contains "DEPLOY_NOW" will trigger a 'git pull' command
                $changes = isset($jsonData['push']['changes']) ? $jsonData['push']['changes'] : false;
                if (false !== $changes) {
                    foreach ($changes as $each) {
                        if (isset($each['new']) && isset($each['new']['target'])) {
                            if (false !== strpos($each['new']['target']['message'], self::DEPLOY_KEYWORD)) {
                                Log::debug("webhook triggered");
                                shell_exec("cd {$this->BASE_PATH}; git pull 2>&1 > {$this->LOG_FILE} &");
                                shell_exec("cd {$this->BASE_PATH}; composer install 2>&1 > {$this->LOG_FILE} &");
                                return 'success';
                            }
                        }
                    }
                } else {
                    return 'no changes; do nothing;';
                }
            } else {
                return response('Forbidden')->setStatusCode(403);
            }
        } else {
            return response('Forbidden')->setStatusCode(403);
        }
    }

    protected function checkHeader(string $header, string $assumption)
    {
        $header = str_replace('-', '_', strtoupper($header));
        $eventKey = isset($_SERVER['HTTP_'.$header]) ? $_SERVER['HTTP_'.$header] : null;
        if ($eventKey && $eventKey === $assumption) {
            return true;
        }
        return false;
    }

    protected function checkIP(string $ip)
    {
        $fields = explode('.', $ip);
        $validIPPrefix1 = ['104', '192', '136', '0'];
        $validIPPrefix2 = ['34', '198', '203', '127'];
        $validIPPrefix3 = ['34', '198', '178', '64'];
        $validIPPrefix4 = ['34', '198', '32', '85'];
        for ($i = 0; $i <= 1; ++$i) {
            //bitbucket文档中给的ip段貌似有误不能完全匹配
            if ($fields[$i] !== $validIPPrefix1[$i]
                && $fields[$i] !== $validIPPrefix2[$i]
                && $fields[$i] !== $validIPPrefix3[$i]
                && $fields[$i] !== $validIPPrefix4[$i]) {
                return false;
            }
        }
        return true;
    }
}
