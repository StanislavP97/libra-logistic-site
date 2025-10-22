<?php
// send.php — общий обработчик формы для 4 языков (RU/RO/EN/UK)
// Отправляет письмо через Gmail SMTP (PHPMailer) и редиректит на thank-you соответствующего языка.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Подключаем PHPMailer (composer require phpmailer/phpmailer)
require __DIR__ . '/vendor/autoload.php';

// === SMTP (Gmail App Password) ===
$SMTP_HOST   = 'smtp.gmail.com';
$SMTP_USER   = 'stanislavgerm@gmail.com';        // логин Gmail
$SMTP_PASS   = 'htfv bfij qmkq kuoa';            // пароль приложения
$SMTP_PORT   = 587;
$SMTP_SECURE = PHPMailer::ENCRYPTION_STARTTLS;

// Кому слать
$TO = 'stanislavgerm@gmail.com';

// Карта Thank-You по языкам
$THANKS_BY_LANG = [
  'ru' => '/ru/thank-you.html',
  'ua' => '/ua/thank-you.html',
  'ro' => '/ro/thank-you.html',
  'en' => '/en/thank-you.html',
];

// --- допускаем только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  safe_redirect('/ru/thank-you.html', ['ok'=>0,'reason'=>'method']);
}

// Антиспам honeypot
if (!empty($_POST['honeypot'])) {
  safe_redirect('/ru/thank-you.html', ['ok'=>0,'reason'=>'spam']);
}

// Утилиты
function val($key, $default='') {
  return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}
function sanitize($s) {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function detect_lang($posted, $thanksMap) {
  $lang = strtolower(trim($posted ?: ''));
  if ($lang && isset($thanksMap[$lang])) return $lang;

  // fallback: попробуем из реферера
  if (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?? '';
    foreach (array_keys($thanksMap) as $code) {
      if (preg_match('~/(?:' . preg_quote($code, '~') . ')(/|$)~', $ref)) {
        return $code;
      }
    }
  }
  return 'ru';
}
function safe_redirect($url, array $params = []) {
  if (!empty($params)) {
    $qs = http_build_query($params);
    if (strpos($url,'?') === false) $url .= '?'.$qs; else $url .= '&'.$qs;
  }
  header('Location: '.$url);
  exit;
}

// Соберём данные формы
$form = [
  'form_name'   => val('form_name','shipping_quote'),
  'lang'        => val('lang',''),
  'full_name'   => val('full_name'),
  'company'     => val('company'),
  'phone'       => val('phone'),
  'email'       => val('email'),
  'weight'      => val('weight'),
  'places'      => val('places'),
  'cargo_type'  => val('cargo_type'),
  'req'         => val('special_requirements'),
  'load_place'  => val('load_place'),
  'destination' => val('destination'),
  'ready_date'  => val('ready_date'),
  'delivery_date'=>val('delivery_date'),
  'comment'     => val('comment'),
  'extra_services' => (isset($_POST['extra_services']) && is_array($_POST['extra_services'])) ? implode(', ', $_POST['extra_services']) : '',
  'reply_channel'  => (isset($_POST['reply_channel'])  && is_array($_POST['reply_channel']))  ? implode(', ', $_POST['reply_channel'])  : '',
  // UTM/клиды/сервис
  'page_path'   => val('page_path'),
  'referrer'    => val('referrer'),
  'utm_source'  => val('utm_source'),
  'utm_medium'  => val('utm_medium'),
  'utm_campaign'=> val('utm_campaign'),
  'utm_content' => val('utm_content'),
  'utm_term'    => val('utm_term'),
  'gclid'       => val('gclid'),
  'fbclid'      => val('fbclid'),
];

// Определим язык редиректа
$lang = detect_lang($form['lang'], $THANKS_BY_LANG);
$THANKS = $THANKS_BY_LANG[$lang];

// Простая валидация обязательных
$errors = [];
if ($form['full_name']   === '') $errors[] = 'name';
if ($form['company']     === '') $errors[] = 'company';
if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'email';
if ($form['weight']      === '') $errors[] = 'weight';
if ($form['places']      === '') $errors[] = 'places';
if ($form['load_place']  === '') $errors[] = 'load_place';
if ($form['destination'] === '') $errors[] = 'destination';

if (!empty($errors)) {
  safe_redirect($THANKS, array_merge([
    'ok' => 0,
    'reason' => 'validation',
    'form' => $form['form_name'],
  ], utm_pack($form)));
}

// Формируем таблицу письма
$rows = [
  'Name'        => $form['full_name'],
  'Company'           => $form['company'],
  'Phone'              => $form['phone'],
  'Email'                        => $form['email'],
  'Weight'              => $form['weight'],
  'Places'     => $form['places'],
  'Cargo type'     => $form['cargo_type'],
  'Requirements'   => $form['req'],
  'Load place'    => $form['load_place'],
  'Destination'    => $form['destination'],
  'Ready date'  => $form['ready_date'],
  'Delivery'   => $form['delivery_date'],
  'Extra services' => $form['extra_services'],
  'Reply via'  => $form['reply_channel'],
  'Comment'           => $form['comment'],
  'UTM Source'                   => $form['utm_source'],
  'UTM Medium'                   => $form['utm_medium'],
  'UTM Campaign'                 => $form['utm_campaign'],
  'UTM Content'                  => $form['utm_content'],
  'UTM Term'                     => $form['utm_term'],
  'gclid'                        => $form['gclid'],
  'fbclid'                       => $form['fbclid'],
  'Referrer'                     => $form['referrer'],
  'Page'                         => $form['page_path'],
];

$html = '<table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px">';
foreach ($rows as $k=>$v) {
  if ($v === '' || $v === null) continue;
  $html .= '<tr><td style="border:1px solid #eee;font-weight:bold;background:#fafafa">'.sanitize($k).'</td><td style="border:1px solid #eee">'.sanitize($v).'</td></tr>';
}
$html .= '</table>';

// === Отправка письма ===
$mail = new PHPMailer(true);
$mail->CharSet  = 'UTF-8';
$mail->Encoding = 'base64';

try {
  $mail->isSMTP();
  $mail->Host       = $SMTP_HOST;
  $mail->SMTPAuth   = true;
  $mail->Username   = $SMTP_USER;
  $mail->Password   = $SMTP_PASS;
  $mail->SMTPSecure = $SMTP_SECURE;
  $mail->Port       = $SMTP_PORT;

  $mail->setFrom($SMTP_USER, 'Libra Logistic');
  $mail->addAddress($TO);
  if (!empty($form['email'])) {
    $mail->addReplyTo($form['email'], $form['full_name']);
  }

  $mail->isHTML(true);
  $mail->Subject = 'Новая заявка с сайта: '.$form['form_name'].' ('.$lang.')';
  $mail->Body    = $html;

  $ok = $mail->send();

  safe_redirect($THANKS, array_merge([
    'ok'   => $ok ? 1 : 0,
    'form' => $form['form_name'],
  ], utm_pack($form)));

} catch (Exception $e) {
  error_log('Mailer Error: '.$mail->ErrorInfo);
  safe_redirect($THANKS, array_merge([
    'ok'=>0,'reason'=>'mail_error','form'=>$form['form_name']
  ], utm_pack($form)));
}

// утилита для прокидывания UTM в qs
function utm_pack(array $f) {
  $keys = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term','gclid','fbclid'];
  $out = [];
  foreach ($keys as $k) $out[$k] = $f[$k] ?? '';
  return $out;
}
