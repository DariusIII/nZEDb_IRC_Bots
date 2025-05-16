<?php

                    declare(strict_types=1);

                    const req_settings = true;

                    $preData = false;

if (isset($_POST['data'])) {
    $data = @unserialize($_POST['data'], ['allowed_classes' => false]);

    if ($data !== false && is_array($data) && isset($data[0]['ident'])) {
        require(__DIR__ . '/../settings.php');
        require(__DIR__ . '/../Classes/DB.php');
        $db = new \nzedb\db\DB();

        $preData = [];
        foreach ($data as $request) {
            $result = $db->queryOneRow(
                sprintf(
                    'SELECT title, groupname, reqid
					                    FROM predb
					                    WHERE reqid = %d
					                    AND groupname = %s
					                    LIMIT 1',
                    $request['reqid'],
                    $db->escapeString($request['group'])
                )
            );

            if ($result !== false) {
                $result['ident'] = $request['ident'];
                $preData[] = $result;
            }
        }
    }
} elseif (isset($_GET['reqid'], $_GET['group']) && is_numeric($_GET['reqid'])) {
    require(__DIR__ . '/../settings.php');
    require(__DIR__ . '/../Classes/DB.php');
    $db = new \nzedb\db\DB();

    $preData = $db->queryOneRow(
        sprintf(
            'SELECT title, groupname, reqid
					            FROM predb
					            WHERE reqid = %d
					            AND groupname = %s
					            LIMIT 1',
            (int)$_GET['reqid'],
            $db->escapeString(trim($_GET['group']))
        )
    );

    if ($preData !== false) {
        $preData['ident'] = 0;
        $preData = [$preData];
    }
}

                    header('Content-type: text/xml');
                    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<requests>\n";

if ($preData !== false) {
    foreach ($preData as $pre) {
        echo sprintf(
            '    <request name="%s" group="%s" reqid="%d" ident="%d"/>' . "\n",
            preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '.', $pre['title']),
            htmlspecialchars($pre['groupname'], ENT_XML1),
            (int)$pre['reqid'],
            (int)$pre['ident']
        );
    }
}

                    echo '</requests>';
