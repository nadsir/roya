<?php

$pin = "A9040330DC58"; // کد پین درگاه

ob_start();
echo '
<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پرداخت آنلاین</title>
    <link href="css/rtl.bootstrap.css" rel="stylesheet">
    <link href="css/reset.css" rel="stylesheet">
    <link href="css/main.css" rel="stylesheet">
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

$url = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
$dir = dirname($url);
$callback = $dir . "/verify.php";

if (!empty($_POST)) {

    $amount = intval($_POST["amount"]);
    if ($amount < 1000) {
        $amount = 1000;
    }

    // توضیحات
    $description = "";
    if (!empty($_POST["name"])) { $description .= "نام و نام خانوادگی: ".$_POST["name"]."\n"; }
    if (!empty($_POST["details"])) { $description .= "توضیحات: ".$_POST["details"]."\n"; }

    $callback .= "?amount=".$amount;

    $apiUrl = "https://panel.aqayepardakht.ir/api/v2/create";

    $fields = [
        "pin" => $pin,
        "amount" => $amount,
        "callback" => $callback,
        "description" => $description,
        "email" => $_POST["email"] ?? "",
        "mobile" => $_POST["mobile"] ?? ""
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);

    if (!$json || !isset($json["status"])) {
        echo '<p class="text-center" style="color:red">خطا در ارتباط با درگاه</p>';
        echo "<pre>$res</pre>";
    } elseif ($json["status"] == "error") {
        echo '<p class="text-center" style="color:red">خطا در ایجاد تراکنش: '.$json["code"].'</p>';
    } elseif ($json["status"] == "success") {
        header("Location: https://panel.aqayepardakht.ir/startpay/".$json["transid"]);
        exit;
    }
}

echo '
<form class="form-horizontal" method="post">
    <div class="form-group">
        <label class="col-sm-4 col-xs-12 hidden-sm hidden-xs">مبلغ (تومان)</label>
        <input type="text" class="form-control numericMask" placeholder="لطفا مبلغ را وارد کنید..." name="amount">
    </div>
    <div class="form-group">
        <label class="col-sm-4 col-xs-12 hidden-sm hidden-xs">نام و نام خانوادگی</label>
        <input type="text" class="form-control" placeholder="نام و نام خانوادگی..." name="name">
    </div>
    <div class="form-group">
        <label class="col-sm-4 col-xs-12 hidden-sm hidden-xs">ایمیل</label>
        <input type="text" class="form-control" placeholder="ایمیل..." name="email">
    </div>
    <div class="form-group">
        <label class="col-sm-4 col-xs-12 hidden-sm hidden-xs">تلفن همراه</label>
        <input type="text" class="form-control" placeholder="تلفن همراه..." name="mobile">
    </div>
    <div class="form-group">
        <label class="col-sm-4 col-xs-12 hidden-sm hidden-xs">توضیحات</label>
        <textarea class="form-control" rows="3" placeholder="متن توضیحات..." name="details"></textarea>
    </div>
    <div class="form-group text-center">
        <button type="submit" class="btn btn-success col-sm-6 col-xs-12 col-sm-offset-3">پرداخت</button>
    </div>
</form>
</div></div></div></div>
<div class="col-xs-1 col-sm-1"></div>
</div></div>

<script src="js/jquery.min.js"></script>
<script src="js/rtl.bootstrap.js"></script>
</body>
</html>';

ob_end_flush();
