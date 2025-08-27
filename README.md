# Web技術各論_前期最終課題
## 手順書記述
【その前に追記】
サービス構築_コード.txtは Dockerfile,compose.yml等のコードの中身をまとめて記述しています。

//ec2インスタンス上で
//gitインストール
sudo yum install git -y

//名前とメールアドレスの設定（メールアドレスは後ほどgithubに登録するメールアドレスと同一のもの）
git config --global user.name "Momo basket"
git config --global user.email "ktc23a31c0006@edu.kyoto-tech.ac.jp"

//ec2インスタンスの中にdockertestディレクトリを作る
mkdir dockertest

//ec2インスタンスの中にdockertestディレクトリがあるか確認
ls -l

//dockertestディレクトリがあれば、ディレクトリ内に入る
cd dockertest

//gitリポジトリを作成する
git init

//dockertestディレクトリ内で.gitフォルダが作成できているか確認
ls -a

//gitを使うその前に…
//Dockerをインストールできていない場合はインストールし、自動的に起動するようにする
sudo yum install -y docker
sudo systemctl start docker
sudo systemctl enable docker

//ec2-userが権限を毎回sudoして実行しないようにするためにdockerコマンドを実行できるようにdockerグループに追加する
sudo usermod -a -G docker ec2-user

//Docker Composeをインストールする
sudo mkdir -p /usr/local/lib/docker/cli-plugins/
sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

//インストールできたかバージョンを確認する
docker compose version

//設定ファイルを作成する
vim compose.yml

※中身は以下↓
services:
  web:
    image: nginx:latest
    ports:
      - 80:80
    volumes:
      - ./nginx/conf.d/:/etc/nginx/conf.d/
      - ./public/:/var/www/public/
      - image:/var/www/upload/image/
    depends_on:
      - php
  php:
    container_name: php
    build:
      context: .
      target: php
    volumes:
      - ./public/:/var/www/public/
      - image:/var/www/upload/image/
  mysql:
    container_name: mysql
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: example_db
      MYSQL_ALLOW_EMPTY_PASSWORD: 1
      TZ: Asia/Tokyo
    volumes:
      - mysql:/var/lib/mysql
    command: >
      mysqld
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --max_allowed_packet=4MB
volumes:
  mysql:
  image:

ESCを押し、:wqで保存する

//イメージをビルドする
Docker compose build

//起動する
docker compose up

//ウェブブラウザでEC2インスタンスのホスト名またはIPアドレスに接続する
http://IPアドレス
→起動できていたらOK

//一度Docker Composeを停止させる
→ログが流れているところでCtrl+Cを押す

//nginxの設定ディレクトリを作成
mkdir nginx
mkdir nginx/conf.d

//nginxの設定ファイルを作成
vim nginx/conf.d/default.conf

※中身は以下↓
server {
    listen       0.0.0.0:80;
    server_name  _;
    charset      utf-8;
    client_max_body_size 6M;

    root /var/www/public;

    location ~ \.php$ {
        fastcgi_pass  php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include       fastcgi_params;
    }

    location /image/ {
        root /var/www/upload;
    }
}

ESCを押し、:wqで保存する

//外部に配信するためのファイルを置くディレクトリを作る
mkdir public

//配信するファイルを作成
vim public/bbsimagetest.php

※中身は以下↓
<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (isset($_POST['body'])) {
  // POSTで送られてくるフォームパラメータ body がある場合

  $image_filename = null;
  if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    // アップロードされた画像がある場合
    if (preg_match('/^image\//', mime_content_type($_FILES['image']['type'])) !== 1) {
      // アップロードされたものが画像ではなかった場合
      header("HTTP/1.1 302 Found");
      header("Location: ./bbsimagetest.php");
    }

    // 元のファイル名から拡張子を取得
    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = $pathinfo['extension'];
    // 新しいファイル名を決める。他の投稿の画像ファイルと重複しないように時間+乱数で決める。
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;
    $filepath =  '/var/www/upload/image/' . $image_filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }

  // insertする
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  // 処理が終わったらリダイレクトする
  // リダイレクトしないと，リロード時にまた同じ内容でPOSTすることになる
  header("HTTP/1.1 302 Found");
  header("Location: ./bbsimagetest.php");
  return;
}

// いままで保存してきたものを取得
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();
?>
<head>
  <title>画像投稿できる掲示板</title>
</head>

<!-- フォームのPOST先はこのファイル自身にする -->
<form method="POST" action="./bbsimagetest.php" enctype="multipart/form-data">
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
      <?= nl2br(htmlspecialchars($entry['body'])) // 必ず htmlspecialchars() すること ?>
      <?php if(!empty($entry['image_filename'])): // 画像がある場合は img 要素を使って表示 ?>
      <div>
        <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
      </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const imageInput = document.getElementById("imageInput");
  imageInput.addEventListener("change", () => {
    if (imageInput.files.length < 1) {
      // 未選択の場合
      return;
    }
    if (imageInput.files[0].size > 5 * 1024 * 1024) {
      // ファイルが5MBより多い場合
      alert("5MB以下のファイルを選択してください。");
      imageInput.value = "";
    }
  });
});
</script>

ESCを押し、:wqで保存する

// MySQLコンテナに入る
docker exec -it mysql mysql -u root example_db

// 以下のSQLでテーブルを作成する（SQL実行画面にて）
CREATE TABLE bbs_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  body TEXT NOT NULL,
  image_filename TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

//Dockerfileの中身を書き換える(dockertestの下でvim Dockerfile)

FROM php:8.4-fpm-alpine AS php

RUN docker-php-ext-install pdo_mysql

RUN install -o www-data -g www-data -d /var/www/upload/image/

RUN echo -e "post_max_size = 5M\nupload_max_filesize = 5M" >> ${PHP_INI_DIR}/php.ini

ESCを押し、:wqで保存する

//イメージをビルドする
Docker compose build


//起動する
docker compose up

//起動したらブラウザで作った.phpファイルにアクセスして確認
http://パブリックIPアドレス/bbsimagetest.php

//もう一度Docker Composeを停止させる
→ログが流れているところでCtrl+Cを押す

//Dockerfile, compose.yml, nginx/ の三つをコミット対象に追加する
git add Dockerfile
git add compose.yml
git add nginx

//コミットできるか試す
git commit -m "start commit!"

//コミットしたか履歴で確認する
git log --stat

commit 609418a203fefbd8a86b411efd96940403f35d29
Author: Momo basket <ktc23a31c0006@edu.kyoto-tech.ac.jp>
Date:   Wed Aug 20 01:16:20 2025 +0000

    start commit!

 Dockerfile                |  8 ++++++++
 compose.yml               | 36 ++++++++++++++++++++++++++++++++++++
 nginx/conf.d/default.conf | 19 +++++++++++++++++++
 3 files changed, 63 insertions(+)

こんな感じだったらOK


//先ほど作った.phpファイルをgitでコミットできるようにする
git add public/bbsimagetest.php

//コミットしてみる
git commit -m "bbsimagetest.phpを追加"

//コミットしてできるか確認
git log --stat

commit b5c5ba7e92314dd55e9110b3d9389adb86b99f73 (HEAD -> main)
Author: Momo basket <ktc23a31c0006@edu.kyoto-tech.ac.jp>
Date:   Wed Aug 20 02:03:29 2025 +0000

    bbsimagetest.phpを追加

 public/bbsimagetest.php | 88 ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
 1 file changed, 88 insertions(+)

こんな感じだったらOK


//ファイルを編集してコミットできるように中身を書き換えてみる
vim public/bbsimagetest.php

formの設定の前に追加する↓
  <head>
    <title>画像投稿できる掲示板</title>
  </head>
 
  <!-- フォームのPOST先はこのファイル自身にする -->
  <form method="POST" action="./bbsimagetest.php" enctype="multipart/form-data">
    <textarea name="body" required></textarea>
    <div style="margin: 1em 0;">
      <input type="file" accept="image/*" name="image" id="imageInput">
      </div>
    <button type="submit">送信</button>
  </form>
 
  <hr>  

→保存する


//編集後、コミットしてみる
git add public/bbsimagetest.php
git commit -m "画像投稿掲示板にtitleを設定"

//コミットを確認
got log --stat

//GitHubを使ってみる
//EC2インスタンスでSSHキーペアを作る
ssh-keygen -t ed25519
→Enterを三回打つ

//作成したキーペアの公開鍵を確認する
cat ~/.ssh/id_ed25519.pub

//登録したアカウントのGitHubからSSH Keysを作る
1.登録したアカウントに入る
2.プロフィールアイコンを押す
3.Settingsを押す
4.SSH and GPG keysを押す
5.New SSH Keysを押す
6.Key typeはそのままで、Keyのところに作成したキーペアの公開鍵を入力する
7.Add SSH Keyをクリック
→以上でアカウントに公開鍵が設定できた

//GitHubリポジトリの作成
1.Githubのトップページ右上の「＋」から「New repository」をクリック
2.「Repository name」を任意の文字で入力する（今回はWeb12_classにしました。）
　※その他の項目はそのまま
3.「Create repository」をクリック
→リポジトリが作成されてリポジトリの画面に遷移する

//Githubで作ったリモートリポジトリをEC2のリポジトリに登録し、設定する
1.「Quick setup — if you’ve done this kind of thing before」と書いてある下に「Set up in Desktop」or ...とありますが、右側のSSHをクリックし、「git@github.com:M785IaR/Web12_class.git」と書いてあるリポジトリURLをコピーする
2.EC2上で「git remote add origin git@github.com:M785IaR/Web12_class.git」とコマンドを打つ
3.git push origin main とコマンドを打つ
以下のようになっていることを確認する↓

[ec2-user@ip-172-31-24-252 dockertest]$ git push origin main
Enumerating objects: 15, done.
Counting objects: 100% (15/15), done.
Compressing objects: 100% (11/11), done.
Writing objects: 100% (15/15), 3.34 KiB | 1.67 MiB/s, done.
Total 15 (delta 3), reused 0 (delta 0), pack-reused 0 (from 0)
remote: Resolving deltas: 100% (3/3), done.
To github.com:M785IaR/Web12_class.git
 * [new branch]      main -> main
[ec2-user@ip-172-31-24-252 dockertest]$    

→その後、Github上でコードを確認するとEC2上で作ったディレクトリやファイルが確認できる

//README.mdファイルの作成
1.Github上の作ったリポジトリの「<>Code」を確認すると画面下に「Add a README」とあるのでクリック
2.「Create README.md」と入力してCommitボタンを押す
3.作成完了
→リポジトリ全体の説明を書くために作成しておく

//イメージをビルドする
Docker compose build

//起動する
docker compose up

最終的に、ブラウザで「http://[awsのパブリックIPv4アドレス]/bbsimagetest.php」と入力し、表示できれば構築完了です。
