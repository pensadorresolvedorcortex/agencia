<?php
namespace PGW\Support;

final class Diagnostics {
    public function evaluate(array $environment): array {
        return [
            'php'=>version_compare((string)($environment['php']??'0'),'8.1','>='),
            'wordpress'=>version_compare((string)($environment['wordpress']??'0'),'6.4','>='),
            'https'=>!empty($environment['https']),
            'cron'=>!empty($environment['cleanup_cron'])&&!empty($environment['link_cron']),
            'database'=>!empty($environment['tables']),
            'images'=>!empty($environment['image_editor']),
        ];
    }
}
