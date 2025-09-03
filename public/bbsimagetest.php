<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (isset($_POST['body'])) {
  $image_filename = null;

  if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    if (preg_match('/^image\//', mime_content_type($_FILES['image']['tmp_name'])) !== 1) {
      header("HTTP/1.1 302 Found");
      header("Location: ./bbsimagetest.php");
      exit;
    }

    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = $pathinfo['extension'];
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;
    $filepath =  '/var/www/upload/image/' . $image_filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }

  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  header("HTTP/1.1 302 Found");
  header("Location: ./bbsimagetest.php");
  exit;
}

$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>画像投稿できる掲示板</title>
</head>
<body>

<form id="postForm" method="POST" action="./bbsimagetest.php" enctype="multipart/form-data">
  <textarea name="body" required></textarea>
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" name="image" id="imageInput">
  </div>
  <button type="submit">送信</button>
</form>

<hr>

<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt>ID</dt>
    <dd><?= $entry['id'] ?></dd>
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <?= nl2br(htmlspecialchars($entry['body'])) ?>
      <?php if(!empty($entry['image_filename'])): ?>
      <div>
        <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
      </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach; ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("postForm");
  const imageInput = document.getElementById("imageInput");

  form.addEventListener("submit", (e) => {
    const file = imageInput.files[0];

    if (!file || file.size <= 5 * 1024 * 1024) {
      //ファイルなし、5MB以下ならそのまま送信
      return;
    }

    e.preventDefault();

    //画像を読み込み
    const reader = new FileReader();
    reader.onload = function(event) {
      const img = new Image();
      img.onload = function() {
        const canvas = document.createElement('canvas');

        const maxWidth = 1024;
        const scale = maxWidth / img.width;
        canvas.width = Math.min(img.width, maxWidth);
        canvas.height = img.height * scale;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        //canvasからJPEG画像Blobを作る
        canvas.toBlob(function(blob) {
          const formData = new FormData(form);
          formData.set('image', blob, 'resized.jpg');

          fetch(form.action, {
            method: 'POST',
            body: formData
          }).then(() => {
            //送信できたらリダイレクトする
            window.location.href = "./bbsimagetest.php";
          });
        }, 'image/jpeg', 0.85);
      };
      img.src = event.target.result;
    };
    reader.readAsDataURL(file);
  });
});
</script>

</body>
</html>

