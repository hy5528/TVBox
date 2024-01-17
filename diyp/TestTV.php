<?php

// ���ݿ��ļ�·��
$dbPath = 'channel_epg.db';

// �򿪻򴴽�SQLite���ݿ�
$db = new SQLite3($dbPath);

// ׼���������SQL���
$createTableSQL = <<<SQL
CREATE TABLE IF NOT EXISTS list (
    item TEXT,
    title TEXT,
    epg TEXT DEFAULT '',  -- epg�ֶ�Ĭ��Ϊ���ַ���
    url TEXT,
    isdel INTEGER
);
SQL;
$db->exec($createTableSQL);

// ���list��
$emptyTableSQL = "DELETE FROM list";
$db->exec($emptyTableSQL);

// ׼����ѯ���м�¼��SQL���
$selectSQL = "SELECT url FROM list WHERE item = :item AND title = :title";
$selectStmt = $db->prepare($selectSQL);

// ׼���������м�¼��SQL���
$updateSQL = "UPDATE list SET url = :new_url WHERE item = :item AND title = :title";
$updateStmt = $db->prepare($updateSQL);

// ׼�������¼�¼��SQL���
$insertSQL = "INSERT INTO list (item, title, epg, url, isdel) VALUES (:item, :title, :epg, :url, :isdel)";
$insertStmt = $db->prepare($insertSQL);

// ��վ����Դ����
$dataSources = [
    'https://ghproxy.net/https://github.com/hy5528/tvbox/blob/main/live.txt',
    'http://home.jundie.top:81/Cat/tv/live.txt'
];

// ��ʼ��isdel������
$isdelCounter = 1;

foreach ($dataSources as $dataSource) {
    // ��ȡ����
    $data = file_get_contents($dataSource);

    // �����ݷָ����
    $lines = explode("\n", $data);

    // ��ǰ������Ŀ
    $currentItem = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue; // ��������

        if (strpos($line, '#genre#') !== false) {
            // �µķ�����Ŀ
            $currentItem = str_replace('#genre#', '', $line);
        } else {
            // �ָ�����URL
            $parts = explode(',', $line, 2);
            if (count($parts) == 2) {
                list($title, $url) = $parts;
                $title = trim($title);
                $url = trim($url);

                // �滻�����е�ָ���ַ���
                $title = preg_replace('/CCTV\-(\d+)\s+.*/', 'CCTV$1', $title);
                $title = str_replace('CCTV-5+ ��������', 'CCTV5+', $title); // ���⴦��CCTV-5+
                $title = str_replace('CCTV-4K ������', 'CCTV4K', $title); // �滻CCTV-4K
                $title = str_replace('CCTV-8K ������', 'CCTV8K', $title); // �滻CCTV-8K

                // �󶨲�������ѯ���м�¼
                $selectStmt->bindValue(':item', $currentItem);
                $selectStmt->bindValue(':title', $title);
                $result = $selectStmt->execute();
                $existingRecord = $result->fetchArray(SQLITE3_ASSOC);

                if ($existingRecord) {
                    // ������ڼ�¼����ϲ�URLs
                    $existingUrls = explode('#', $existingRecord['url']);
                    if (!in_array($url, $existingUrls)) {
                        // ֻ�е�URL��������URLs��ʱ�źϲ�
                        $newUrl = $existingRecord['url'] . '#' . $url;
                        $updateStmt->bindValue(':new_url', $newUrl);
                        $updateStmt->bindValue(':item', $currentItem);
                        $updateStmt->bindValue(':title', $title);
                        $updateStmt->execute();
                    }
                } else {
                    // ��������ڼ�¼��������¼�¼
                    $insertStmt->bindValue(':item', $currentItem);
                    $insertStmt->bindValue(':title', $title);
                    $insertStmt->bindValue(':epg', ''); // epg�ֶ�Ϊ��
                    $insertStmt->bindValue(':url', $url);
                    $insertStmt->bindValue(':isdel', $isdelCounter);
                    $insertStmt->execute();
                }
                
                // ����isdel������
                $isdelCounter++;
            }
        }
    }
}

// �ر����ݿ�����
$db->close();

echo "Data processed successfully.";

?>
