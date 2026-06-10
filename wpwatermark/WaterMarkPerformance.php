<?php
/**
 * WaterMark Performance Monitor Class
 * 
 * Monitors and logs watermark processing performance
 */
class WaterMarkPerformance {
    private $start_time;
    private $start_memory;
    private $log_file;

    /**
     * 是否启用性能日志（默认关闭，避免长期占用磁盘）
     */
    public static function isEnabled(): bool {
        return (bool) apply_filters('wpwatermark_performance_logging_enabled', false);
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->log_file = plugin_dir_path(__FILE__) . 'logs/watermark_performance.log';

        if (!self::isEnabled()) {
            return;
        }

        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
    }
    
    /**
     * Start monitoring
     */
    public function startMonitoring() {
        if (!self::isEnabled()) {
            return;
        }

        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage();
    }
    
    /**
     * End monitoring and log results
     * 
     * @param string $operation Operation being monitored
     * @param array $metadata Additional metadata to log
     */
    public function endMonitoring($operation, $metadata = []) {
        if (!self::isEnabled()) {
            return;
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        
        $duration = round(($end_time - $this->start_time) * 1000, 2); // Convert to milliseconds
        $memory_used = round(($end_memory - $this->start_memory) / 1024 / 1024, 2); // Convert to MB
        
        $log_data = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'operation' => $operation,
            'duration_ms' => $duration,
            'memory_mb' => $memory_used
        ], $metadata);
        
        $this->logPerformance($log_data);
    }
    
    /**
     * Log performance data
     */
    private function logPerformance($data) {
        if (!self::isEnabled()) {
            return;
        }

        $log_entry = json_encode($data) . "\n";
        
        if (file_exists($this->log_file) && filesize($this->log_file) > 5 * 1024 * 1024) { // 5MB limit
            $this->rotateLogFile();
        }
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
    }
    
    /**
     * Rotate log file when it gets too large
     */
    private function rotateLogFile() {
        $backup_file = $this->log_file . '.' . date('Y-m-d-H-i-s') . '.bak';
        rename($this->log_file, $backup_file);
        
        // Keep only last 5 backup files
        $backup_files = glob($this->log_file . '.*.bak');
        if (count($backup_files) > 5) {
            usort($backup_files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            $files_to_delete = array_slice($backup_files, 5);
            foreach ($files_to_delete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get performance statistics
     */
    public function getStatistics($period = '24h') {
        $stats = [
            'total_operations' => 0,
            'avg_duration' => 0,
            'avg_memory' => 0,
            'max_duration' => 0,
            'max_memory' => 0
        ];
        
        if (!file_exists($this->log_file)) {
            return $stats;
        }
        
        $cutoff_time = strtotime('-' . $period);
        $total_duration = 0;
        $total_memory = 0;
        
        $handle = fopen($this->log_file, 'r');
        while (($line = fgets($handle)) !== false) {
            $data = json_decode($line, true);
            if (!$data) continue;
            
            $log_time = strtotime($data['timestamp']);
            if ($log_time < $cutoff_time) continue;
            
            $stats['total_operations']++;
            $total_duration += $data['duration_ms'];
            $total_memory += $data['memory_mb'];
            
            $stats['max_duration'] = max($stats['max_duration'], $data['duration_ms']);
            $stats['max_memory'] = max($stats['max_memory'], $data['memory_mb']);
        }
        fclose($handle);
        
        if ($stats['total_operations'] > 0) {
            $stats['avg_duration'] = round($total_duration / $stats['total_operations'], 2);
            $stats['avg_memory'] = round($total_memory / $stats['total_operations'], 2);
        }
        
        return $stats;
    }
    
    /**
     * Clean old log entries
     */
    public function cleanOldLogs($days = 30) {
        if (!self::isEnabled() || !file_exists($this->log_file)) {
            return;
        }
        
        $cutoff_time = strtotime('-' . $days . ' days');
        $temp_file = $this->log_file . '.temp';
        
        $handle = fopen($this->log_file, 'r');
        $temp_handle = fopen($temp_file, 'w');
        
        while (($line = fgets($handle)) !== false) {
            $data = json_decode($line, true);
            if (!$data) continue;
            
            $log_time = strtotime($data['timestamp']);
            if ($log_time >= $cutoff_time) {
                fwrite($temp_handle, $line);
            }
        }
        
        fclose($handle);
        fclose($temp_handle);
        
        rename($temp_file, $this->log_file);
    }
} 