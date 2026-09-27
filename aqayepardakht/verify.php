<?php

if (!isset($_POST["transid"]) || !isset($_POST["status"])) {
    die("پارامترهای ارسالی معتبر نیست.");
}

$status = $_POST[ "status" ];
$transid = $_POST[ "transid" ];
$amount  = $_GET["amount"];

if ( $status === "1" ) {

$pin = "A9040330DC58"; // کد پین درگاه

$fields = [
    "pin"     => $pin,
    "transid" => $transid,
    "amount"  => $amount
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://panel.aqayepardakht.ir/api/v2/verify");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);

$json = json_decode($res, true);

echo '<!DOCTYPE html>
<html lang="fa">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پرداخت آنلاین</title>
	<link rel="shortcut icon" href="images/logo.png" />
    <link href="css/rtl.bootstrap.css" rel="stylesheet">
    <link href="css/reset.css" rel="stylesheet">
    <link href="css/main.css" rel="stylesheet">
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
    <div class="container">
      <div class="row">
        <div class="col-xs-1 col-sm-1"></div>
        <div class="col-xs-10 col-sm-10">
          <div class="main clearfix">
            <div class="col-xs-12">
              <img src="images/bankLogos.png" class="BankLogos img-responsive">
              <h2 class="titleService">پرداخت آنلاین</h2>
            </div>
            <div class="col-xs-12">
              <div class="rightBox clearfix">
                <div class="payForm col-xs-12">

';
if ( $json["code"] == "1") {
  echo '<p class="text-center" style="color:green">پرداخت شما با موفقیت انجام شد !</p></br><p class="text-center">کد پیگیری تراکنش : ' . $transid . '</p>';
} else if ( $json["code"] == "0" ) {
  echo '<p class="text-center" style="color:red">متاسفیم! پرداخت شما موفقیت آمیز نبود.</p></br><p class="text-center">کد پیگیری تراکنش : ' . $transid . '</p><hr><p class="text-center" style="color:orange">درصورت کسر شدن موجودی از حسابتان ،‌مبلغ کسر شده طی ۱۵ دقیقه الی ۷۲ ساعت کاری آینده از سمت بانک برگشت داده میشود. </p>';
} else {
  echo '<p class="text-center" style="color:red">کد خطا : ' . $json["code"] . '</p>';
}
echo '
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-xs-1 col-sm-1"></div>
      </div>
    </div>
    <script src="js/jquery.min.js"></script> 
    <script src="js/rtl.bootstrap.js"></script>
  </body>
</html>';

} else {

echo '
<!DOCTYPE html>
<html lang="fa">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>پرداخت آنلاین</title>
  <link rel="shortcut icon" href="images/logo.png" />
  <link href="css/rtl.bootstrap.css" rel="stylesheet">
  <link href="css/reset.css" rel="stylesheet">
  <link href="css/main.css" rel="stylesheet">
  <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body>
  <div class="container">
    <div class="row">
      <div class="col-xs-1 col-sm-1"></div>
      <div class="col-xs-10 col-sm-10">
        <div class="main clearfix">
          <div class="col-xs-12">
            <img src="images/bankLogos.png" class="BankLogos img-responsive">
            <h2 class="titleService">پرداخت آنلاین</h2>
          </div>
          <div class="col-xs-12">
            <div class="rightBox clearfix">
              <div class="payForm col-xs-12">
                <p class="text-center" style="color:red">متاسفیم! پرداخت شما موفقیت آمیز نبود.</p></br>
                <p class="text-center">کد پیگیری تراکنش : ' . $transid . '</p>
                <hr>
                <p class="text-center" style="color:orange">درصورت کسر شدن موجودی از حسابتان ،‌مبلغ کسر شده طی ۱۵ دقیقه
                  الی ۷۲ ساعت کاری آینده از سمت بانک برگشت داده میشود. </p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xs-1 col-sm-1"></div>
    </div>
  </div>
  <script src="js/jquery.min.js"></script>
  <script src="js/rtl.bootstrap.js"></script>
</body>

</html>
';
}