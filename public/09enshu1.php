   <?php
   $dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
  
   if (isset($_POST['body'])){
   // POSTで送られてくるフォームパラメータbodyがある場合
  
   // insertする
   $insert_sth = $dbh->prepare("INSERT INTO hogehoge (text) VALUES (:body)");
   $insert_sth->execute(array(
      ':body' => $_POST['body'],
  ));
  } 
  // 行数をカウント
  $select_sth = $dbh->prepare('SELECT * FROM hogehoge ORDER BY created_at DESC'    );
  $select_sth->execute();
  ?>
 
  <div style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid     #ccc;">
    <h1>アクセスログ</h1>
  </div>
 
  <?php foreach($select_sth as $log): ?>
    <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px soli    d #ccc;">
      <dt>送信日時</dt>
      <dd><?= $log['created_at'] ?></dd>
      <dt>送信内容</dt>
      <dd><?= htmlspecialchars($log['text'], ENT_QUOTES, 'UTF-8') ?></dd>
    </dl>
  <?php endforeach ?>
