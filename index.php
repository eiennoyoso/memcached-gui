<?php

switch (filter_input(INPUT_GET, 'page')) {
    default:
        $renderer = new Renderer();
        $renderer->render();
        break;
    case 'keys':
        [$host, $port] = explode(':', filter_input(INPUT_GET, 'address'));
        $m = new Mem($host, $port);
        $m->connect();

        $keys = $m->getKeys();
        ksort($keys);
        echo json_encode($keys);
        header('Content-type: application/json');
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

    private function req(string $command): \Generator
    {
        socket_write($this->sock, $command . "\n");

        while (true) {
            $line = trim(socket_read($this->sock, 2048, PHP_NORMAL_READ));

            if (empty($line)) {
                break;
            }

            yield $line;
        }
    }

    public function getKeys(): array
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

            $key = rawurldecode($keyMeta['key']);
            unset($keyMeta['key']);
            
            $expiration = (int) $keyMeta['exp'];
            if ($expiration > 60*60*24*30) {
                $expiration = date('Y-m-d H:i:s');
            }

            $la = (int) $keyMeta['la'];
            if ($la > 60*60*24*30) {
                $la = date('Y-m-d H:i:s');
            }

            $keyMeta['exp'] = $expiration;
            $keyMeta['la'] = $la;
            $keys[$key] = $keyMeta;
        }

        return $keys;
    }
}

class Renderer {
    public function render() {
        ?>
            <html>
            <head>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/underscore.js/1.13.2/underscore-min.js" integrity="sha512-anTuWy6G+usqNI0z/BduDtGWMZLGieuJffU89wUU7zwY/JhmDzFrfIZFA3PY7CEX4qxmn3QXRoXysk6NBh5muQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
            <style>
                html, body {
                    margin: 5px;
                }
                body, td {
                    font-size: 9px;
                }
            </style>
            </head>
            <body>
                <form><input type="text" name="arrdess" value="127.0.0.1:11211" class="form-control" /></form>
                <div id="keys"></div>
                <script type="x-template">
                    <table cellpadding="0" cellspacing="0" class="table table-bordered table-hover table-striped">
                        <thead>
                        <tr>
                            <th>key</th>
                            <th>exp</th>
                            <th>la</th>
                            <th>cas</th>
                            <th>fetch</th>
                            <th>cls/th>
                            <th>size</th>
                        </tr>
                        </thead>
                        <tbody>
                        <% for(key is keys) { %>
                            <tr>
                                <td><%=key%></td>
                                <td><%=keys[key]['exp']%></td>
                                <td><%=keys[key]['la']%></td>
                                <td><%=keys[key]['cas']%></td>
                                <td><%=keys[key]['fetch']%></td>
                                <td><%=keys[key]['cls']%></td>
                                <td><%=keys[key]['size']%></td>
                            </tr>
                        <% } %>
                        </tbody>
                    </table>
                    </script>
            </body>
            <script type="text/javascript">
                $(function() {
                    let loadKeys = async function (address) {
                        return fetch("/?page=keys&address=" + address);
                    };
                });
            </script>
        </html>
        <?php
    }
}
