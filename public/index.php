<?php

date_default_timezone_set('UTC');

switch (filter_input(INPUT_GET, 'page')) {
    case 'keys':
        $address = filter_input(INPUT_GET, 'address');
        if (empty($address)) {
            throw new \Exception('Invalid address');
        }

        [$host, $port] = explode(':', $address);
        $m = new Mem($host, $port);
        $m->connect();

        $keys = $m->getKeys();
        ksort($keys);
        header('Content-type: application/json');
        echo json_encode([
            'keys' => $keys,
            'currentTime' => date('Y-m-d H:i:s'),
        ]);
        
        break;
}

class Mem {
    private Socket $sock;

    public function __construct(private readonly string $host, private readonly string $port) {}

    public function connect(): void
    {
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($this->sock, $this->host, $this->port);
    }

    private function req(string $command): array
    {
        $lines = [];

        socket_write($this->sock, $command . "\n");

        while (true) {
            $line = socket_read($this->sock, 2048, PHP_NORMAL_READ);

            if ($line === '' || $line === false || trim($line) === 'END') {
                break;
            }

            if ($line === "\r" || $line === "\n") {
                continue;
            }

            $line = trim($line);

            if (strpos($line, 'ERROR') === 0 || strpos($line, 'CLIENT_ERROR') === 0) {
                throw new \Exception($line);
            }

            $lines[] = $line;
        }

        return $lines;
    }

    public function getKeys(): array
    {
        $keys = [];

        try {
            $keys = $this->getLruCrawlerMetaDump();
        } catch (\Throwable $e) {}

        $slabs = $this->getStatsSlabs();
        foreach (array_keys($slabs) as $slabId) {
            $slabKeys = $this->getSlabKeys($slabId);
            $keys += $slabKeys;
        }
        

        return $keys;
    }

    public function getStatsSlabs(): array
    {
        $slabs = [];

        $slabRows = $this->req('stats slabs');
        
        foreach ($slabRows as $slabRow) {
            if (!preg_match('/^STAT (\d+):(\w+) (\w+)$/', trim($slabRow), $matches)) {
                continue;
            }

            $slabs[$matches[1]][$matches[2]] = $matches[3];      
        }

        return $slabs;
    }

    public function getSlabKeys(int $slabId): array
    {
        $keys = [];

        $rows = $this->req('stats cachedump ' . $slabId . ' 0');

        foreach ($rows as $row) {
            if (!preg_match('/^ITEM (.+) \[(\d+) b; (\d+) s\]$/', $row, $matches)) {
                continue;
            }

            $expiration = (int) $matches[3];
            if ($expiration > 60*60*24*30) {
                $expiration = date('Y-m-d H:i:s', $expiration);
            }

            $keys[$matches[1]] = [
                'size' => $matches[2],
                'exp' => $expiration,
            ];
        }

        return $keys;
    }
    
    public function getLruCrawlerMetaDump(): array
    {
        $keys = [];
        foreach ($this->req("lru_crawler metadump all") as $line) {
            if (empty($line)) {
                continue;
            }

            $keyMeta = array_reduce(
                explode(' ', $line),
                function(array $carry, string $pair) {
                    $pairParts = explode('=', $pair);
                    if (count($pairParts) === 2 ){
                        $carry[$pairParts[0]] = $pairParts[1];
                    }
                    return $carry;
                },
                []
            );

            if (empty($keyMeta['key'])) {
                continue;
            }

            $key = rawurldecode($keyMeta['key']);
            unset($keyMeta['key']);
            
            $expiration = (int) $keyMeta['exp'];
            if ($expiration > 60*60*24*30) {
                $expiration = date('Y-m-d H:i:s', $expiration);
            }

            $la = (int) $keyMeta['la'];
            if ($la > 60*60*24*30) {
                $la = date('Y-m-d H:i:s', $la);
            }

            $keyMeta['exp'] = $expiration;
            $keyMeta['la'] = $la;
            $keys[$key] = $keyMeta;
        }

        return $keys;
    }
}
