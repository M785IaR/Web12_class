<?php
$ch = curl_init('https://kyoto-tech.ac.jp/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);

print(htmlspecialchars($res));
