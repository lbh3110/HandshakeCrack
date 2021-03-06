<?php
/**
 * User: n3d.b0y
 * Email: n3d.b0y@gmail.com
 */
namespace pineapple;

putenv('LD_LIBRARY_PATH='.getenv('LD_LIBRARY_PATH').':/sd/lib:/sd/usr/lib');
putenv('PATH='.getenv('PATH').':/sd/usr/bin:/sd/usr/sbin');

class HandshakeCrack extends Module
{
    const PATH_MODULE = '/pineapple/modules/HandshakeCrack';
    const PATH_MODULE_SD = '/sd/modules/HandshakeCrack';
    const BASH_SCRIP_SEND_HANDSHAKE = '/scripts/handshake.sh';
    const BASH_SCRIP_CONVERTER = '/scripts/converter.sh';
    const PYTHON_SCRIPT_PARSEG_PCAP = '/scripts/parser_pcap.py';

    public function route()
    {
        switch ($this->request->action) {
            case 'getInfo':
                $this->getInfo();
                break;
            case 'getStatus':
                $this->getStatus();
                break;
            case 'managerDependencies':
                $this->managerDependencies();
                break;
            case 'statusDependencies':
                $this->statusDependencies();
                break;
            case 'getSettings':
                $this->getSettings();
                break;
            case 'setSettings':
                $this->setSettings();
                break;
            case 'getHandshakeFiles':
                $this->getHandshakeFiles();
                break;
            case 'getHandshakeInfo':
                $this->getHandshakeInfo();
                break;
            case 'sendHandshake':
                $this->sendHandshake();
                break;
            case 'converter':
                $this->converter();
                break;
            case 'isConnected':
                $this->isConnected();
                break;
        }
    }

    protected function getPathModule() {
        $isAvailable = $this->isSDAvailable();

        if ($isAvailable) {
            return self::PATH_MODULE_SD;
        }

        return self::PATH_MODULE;
    }

    protected function getInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/HandshakeCrack/module.info"));
        $this->response = array('title' => $moduleInfo->title, 'version' => $moduleInfo->version);
    }

    private function getStatus()
    {
        if (!file_exists('/tmp/HandshakeCrack.progress')) {
            if ($this->checkDependencies()) {
                $this->response = array(
                    "installed" => false, "install" => "Remove",
                    "installLabel" => "danger", "processing" => false
                );
            } else {
                $this->response = array(
                    "installed" => true, "install" => "Install",
                    "installLabel" => "success", "processing" => false
                );
            }
        } else {
            $this->response = array(
                "installed" => false, "install" => "Installing...",
                "installLabel" => "warning", "processing" => true
            );
        }
    }

    protected function checkDependencies()
    {
        return ((exec("which curl") == '' ? false : true) && ($this->uciGet("handshakecrack.module.installed")));
    }

    private function managerDependencies()
    {
        if (!$this->checkDependencies()) {
            $this->execBackground("/pineapple/modules/HandshakeCrack/scripts/dependencies.sh install");
            $this->response = array('success' => true);
        } else {
            $this->execBackground("/pineapple/modules/HandshakeCrack/scripts/dependencies.sh remove");
            $this->response = array('success' => true);
        }
    }

    private function statusDependencies()
    {
        if (!file_exists('/tmp/HandshakeCrack.progress')) {
            $this->response = array('success' => true);
        } else {
            $this->response = array('success' => false);
        }
    }

    private function getSettings()
    {
        $settings = array(
            'email' => $this->uciGet("handshakecrack.settings.email")
        );
        $this->response = array('settings' => $settings);
    }

    private function setSettings()
    {
        $settings = $this->request->settings;
        $this->uciSet("handshakecrack.settings.email", $settings->email);
    }

    private function getHandshakeFiles()
    {
        exec("find -L /pineapple/modules/ -type f -name \"*.**cap\" 2>&1", $dir1);
        exec("find -L /sd/modules/ -type f -name \"*.**cap\" 2>&1", $dir2);
        exec("find -L /tmp/ -type f -name \"*.**cap\" 2>&1", $dir3);

        if (stristr($dir2[0], ' No such file or ')) {
            $dir2 = [];
        }

        $this->response = array("files" => array_merge($dir1, $dir2, $dir3));
    }

    private function getHandshakeInfo()
    {
        if (!empty($this->request->path)) {
            exec('python ' . $this->getPathModule() . self::PYTHON_SCRIPT_PARSEG_PCAP . ' -i ' . $this->request->path, $output);
            $outputArr = preg_split('/\s+/', $output[0]);

            if (!empty($outputArr)) {
                $this->response = array(
                    'success' => true, 'bssid' => strtoupper($outputArr[0]),
                    'essid' => $outputArr[1]
                );
            } else {
                $this->response = array('success' => false);
            }
        } else {
            $this->response = array('success' => false);
        }
    }

    private function sendHandshake()
    {
        exec($this->getPathModule() . self::BASH_SCRIP_SEND_HANDSHAKE . " " . $this->request->file, $output);
        $this->response = array('output' => $output);
    }

    private function converter()
    {
        exec($this->getPathModule() . self::BASH_SCRIP_CONVERTER . " " . $this->request->file, $output);

        $this->response = array('output' => $output[0]);
    }

    public function isConnected()
    {
        $connected = @fsockopen("google.com", 80);

        if ($connected) {
            $this->response = array('success' => true);
        } else {
            $this->response = array('success' => false);
        }
    }
}