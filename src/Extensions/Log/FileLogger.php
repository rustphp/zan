<?php
/**
 * Created by IntelliJ IDEA.
 * User: nuomi
 * Date: 16/5/24
 * Time: 下午2:55
 */
namespace Zan\Framework\Extensions\Log;
use Zan\Framework\Foundation\Core\Config;
use Zan\Framework\Foundation\Core\Path;
use Zan\Framework\Extensions\Log\Writer\FileWriter;
use Zan\Framework\Extensions\Log\Formatter\LineFormatter;

class FileLogger extends BaseLogger {
    /**
     * FileLogger constructor.
     *
     * @param string $config_key
     */
    public function __construct($config_key) {
        parent::__construct($config_key);
        $this->config['path'] = $this->getLogPath($this->config);
        $formatter = new LineFormatter();
        $writer = new FileWriter($this->config['path'], $this->config['async']);
        $this->setHandler($formatter, $writer);
    }

    /**
     * @param $config
     * @return string
     */
    private function getLogPath($config) {
        $logBasePath = '';
        $path = ltrim($config['path'], '/');
        if ($config['factory'] === 'log') {
            $logBasePath = Config::get('path.log');
        } else {
            if ($config['factory'] === 'file') {
                $logBasePath = Path::getLogPath();
            }
        }
        $path = $logBasePath . $path;
        return $path;
    }
}
