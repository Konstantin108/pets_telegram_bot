<?
header('Content-Type: text/html; charset=utf-8');

$bot_token = '***';
$data = file_get_contents('php://input');
$data = json_decode($data, true);

$main_chat_id = '***';
$bot_state = '';

$keyboard = json_encode(array(
    'keyboard' => array(
        array(
            array(
                'text' => 'Обо мне'
            ),
            array(
                'text' => 'Список команд'
            ),
        ),
        array(
            array(
                'text' => 'Курага'
            ),
            array(
                'text' => 'Василиса'
            ),
        )
    ),
    "resize_keyboard" => true
));

$inline_keyboard = json_encode(array(
    'inline_keyboard' => array(
        array(
            array(
                'text' => '👍',
                'callback_data' => 'like',
            ),

            array(
                'text' => '👎',
                'callback_data' => 'unlike',
            ),
        )
    ),
));

if ($data) {
    $chat_id = $data['message']['from']['id'];
    $user_name = $data['message']['from']['username'];
    $first_name = $data['message']['from']['first_name'];
    $last_name = $data['message']['from']['last_name'];
    $text = trim($data['message']['text']);

    if (isset($data['callback_query'])) {
        $chat_id = $data['callback_query']['from']['id'];
        $user_name = $data['callback_query']['from']['username'];
        $first_name = $data['callback_query']['from']['first_name'];
        $last_name = $data['callback_query']['from']['last_name'];
        $callback_data = $data['callback_query']['data'];
    }

    $text_array = explode(" ", $text);
    $chat_data = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'chat_id' => $chat_id
    ];
    $bot_state = get_bot_state($chat_data);
    $bot_state = $bot_state['action'];

    switch ($text) {
        case '/start':
            $method = '/sendMessage';
            $text_return = "Бот запущен";
            break;
        case 'Список команд':
            $method = '/sendMessage';
            $text_return = "Привет, $first_name $last_name, вот команды, что я понимаю:
/Обо мне - информация обо мне
/Список команд - что я умею
/Курага - показать фото Кураги
/Василиса - показать фото Василисы
";
            break;
        case 'Обо мне':
            $method = '/sendMessage';
            $text_return = "Любимцы бот:
Я - простой бот, который умеет только показывать фотки шикарных кошечек =)        
";
            break;
        case 'Василиса':
            $method = '/sendPhoto';
            $cat_name = 'vasilisa';
            $img_data = select_random_photo($cat_name);
            $text_return = $img_data['caption'];
            if ($chat_id != $main_chat_id) {
                $msg_for_admin = "
$first_name $last_name сейчас любуется Васечкой =)        
        ";
                message_to_telegram($bot_token, $main_chat_id, $msg_for_admin, '/sendMessage', $keyboard);
            }
            $keyboard = $inline_keyboard;
            break;
        case 'Курага':
            $method = '/sendPhoto';
            $cat_name = 'kuraga';
            $img_data = select_random_photo($cat_name);
            $text_return = $img_data['caption'];
            if ($chat_id != $main_chat_id) {
                $msg_for_admin = "
$first_name $last_name сейчас любуется Курагой =)        
        ";
                message_to_telegram($bot_token, $main_chat_id, $msg_for_admin, '/sendMessage', $keyboard);
            }
            $keyboard = $inline_keyboard;
            break;
        default:
            $method = '/sendMessage';
            $text_return = "Используй кнопки с командами";
    };

    switch ($callback_data) {
        case 'like':
            $method = '/sendMessage';
            $text_return = "Вам нравится это фото";
            break;
        case 'unlike':
            $method = '/sendMessage';
            $text_return = "Вам не нравится это фото";
            break;
    }

    message_to_telegram($bot_token, $chat_id, $text_return, $method, $keyboard, $img_data['img']);
    $chat_data['action'] = $text;
    set_bot_state($chat_id, $chat_data);

} else {
    $users = scandir(__DIR__ . '/users/');
    foreach ($users as $user) {
        if (preg_match('/\.json/', $user)) {
            $user_data = json_decode(file_get_contents(__DIR__ . '/users/' . $user), true);
            $text_return = "Скучаешь, {$user_data['first_name']}? Вот полюбуйся!";
//            if ($user_data['chat_id'] != $main_chat_id) {
            message_to_telegram($bot_token, $user_data['chat_id'], $text_return, '/sendMessage', $keyboard);
            $img_data = special_notification();
            $text_return = $img_data['caption'];
            message_to_telegram($bot_token, $user_data['chat_id'], $text_return, '/sendPhoto', $inline_keyboard, $img_data['img']);
//            }
            $chat_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'chat_id' => $chat_id,
                'action' => '/test'
            ];
        }
    }
}

function special_notification()
{
    $dir = scandir(__DIR__ . '/cats/');
    $arr = [];
    foreach ($dir as $cat_dir_name) {
        if (preg_match('/\w/', $cat_dir_name)) {
            $arr[] = $cat_dir_name;
        }
    }
    $random_cat_dir_name = $arr[rand(0, sizeof($arr) - 1)];
    return select_random_photo($random_cat_dir_name);
}

function select_random_photo($cat_name)
{
    $dir = scandir(__DIR__ . '/cats/' . $cat_name);
    $arr = [];
    foreach ($dir as $photo) {
        if (preg_match('/\.JPG/', $photo)) {
            $arr[] = $photo;
        }
    }
    $random_photo = $arr[rand(0, sizeof($arr) - 1)];
    $caption = str_replace('.JPG', '', $random_photo);
    $img = __DIR__ . "/cats/" . $cat_name . "/" . iconv('UTF-8', "Windows-1251", $random_photo);   //на хостинге iconv не нужен
    return ['caption' => $caption, 'img' => $img];
}

function message_to_telegram($bot_token, $chat_id, $text, $method, $reply_markup, $img = null)
{
    $ch = curl_init();
    $curl_postfields = [
        'chat_id' => $chat_id,
        'parse_mode' => 'HTML',
        'text' => $text,
        'reply_markup' => $reply_markup,
    ];
    if ($img) {
        $curl_postfields = array_merge($curl_postfields, [
            'photo' => curl_file_create($img),
            'caption' => $text,
        ]);
    }
    $ch_post = [
        CURLOPT_URL => 'https://api.telegram.org/bot' . $bot_token . $method,
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => $curl_postfields
    ];
    curl_setopt_array($ch, $ch_post);
    curl_exec($ch);
}

function set_bot_state($chat_id, $chat_data)
{
    file_put_contents(__DIR__ . '/users/' . $chat_id . '.json', json_encode($chat_data));
}

function get_bot_state($chat_data)
{
    if (file_exists(__DIR__ . '/users/' . $chat_data['chat_id'] . '.json')) {
        return json_decode(file_get_contents(__DIR__ . '/users/' . $chat_data['chat_id'] . '.json'), true);
    } else {
        return '';
    }
}





