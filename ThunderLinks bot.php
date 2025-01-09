<?php  
  
// Configuration  
$telegramBotToken = "7948373199:AAHVIj43CHGiGp2nbRpVZT3a9Dz72ZrbIwk";  
$apiKeysFile = 'api_keys.json';  
$shortenedLinksFile = 'shortened_links.json';  
$userSettingsFile = 'user_settings.json';  
  
// Load existing data  
$apiKeys = file_exists($apiKeysFile) ? json_decode(file_get_contents($apiKeysFile), true) : [];  
$shortenedLinks = file_exists($shortenedLinksFile) ? json_decode(file_get_contents($shortenedLinksFile), true) : [];  
$userSettings = file_exists($userSettingsFile) ? json_decode(file_get_contents($userSettingsFile), true) : [];  
  
// Function to save API keys  
function saveApiKeys() {  
   global $apiKeys, $apiKeysFile;  
   file_put_contents($apiKeysFile, json_encode($apiKeys));  
}  
  
// Function to save shortened links  
function saveShortenedLinks() {  
   global $shortenedLinks, $shortenedLinksFile;  
   file_put_contents($shortenedLinksFile, json_encode($shortenedLinks));  
}  
  
// Function to save user settings  
function saveUserSettings() {  
   global $userSettings, $userSettingsFile;  
   file_put_contents($userSettingsFile, json_encode($userSettings));  
}  
  
// Function to send a message  
function sendMessage($chatId, $message, $replyToMessageId = null) {  
   global $telegramBotToken;  
   $url = "https://api.telegram.org/bot$telegramBotToken/sendMessage";  
   $data = [  
      'chat_id' => $chatId,  
      'text' => $message,  
      'parse_mode' => 'Markdown',  
      'disable_web_page_preview' => true  
   ];  
   if ($replyToMessageId) {  
      $data['reply_to_message_id'] = $replyToMessageId;  
   }  
   file_get_contents($url . '?' . http_build_query($data));  
}  
  
// Function to shorten a URL  
function shortenUrl($urlToShorten, $chatId) {  
   global $apiKeys, $shortenedLinks;  
   if (isset($apiKeys[$chatId])) {  
      $apiKey = $apiKeys[$chatId];  
      $apiUrl = "https://thunderlinks.site/api?api=$apiKey&url=" . urlencode($urlToShorten) . "&alias=" . urlencode(generateRandomString());  
      $response = file_get_contents($apiUrl);  
      if ($response === FALSE) {  
        return $urlToShorten;  
      }  
      $responseData = json_decode($response, true);  
      if (isset($responseData['shortenedUrl'])) {  
        $shortenedUrl = $responseData['shortenedUrl'];  
        if (!isset($shortenedLinks[$chatId])) {  
           $shortenedLinks[$chatId] = [];  
        }  
        $shortenedLinks[$chatId][] = $shortenedUrl;  
        saveShortenedLinks();  
        return $shortenedUrl;  
      } else {  
        return $urlToShorten;  
      }  
   } else {  
      sendMessage($chatId, "Please Set Your Api Using /api");  
      return $urlToShorten;  
   }  
}  
  
// Function to generate a random string  
function generateRandomString($length = 8) {  
   return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);  
}  
  
// Function to process text and shorten URLs  
function processText($text, $chatId) {  
   $urlPattern = '/https?:\/\/[^\s]+/i';  
   $textWithShortenedUrls = $text;  
   if (preg_match_all($urlPattern, $text, $matches)) {  
      foreach ($matches[0] as $url) {  
        $shortenedUrl = shortenUrl($url, $chatId);  
        $textWithShortenedUrls = str_replace($url, $shortenedUrl, $textWithShortenedUrls);  
      }  
   }  
   return $textWithShortenedUrls;  
}  
  
// Function to add header and footer to a message  
function addHeaderAndFooter($message, $chatId) {  
   global $userSettings;  
   $header = $userSettings[$chatId]['header'] ?? '';  
   $footer = $userSettings[$chatId]['footer'] ?? '';  
   $headerEnabled = $userSettings[$chatId]['off_header'] ?? 'no';  
   $footerEnabled = $userSettings[$chatId]['off_footer'] ?? 'no';  
   $processedHeader = ($headerEnabled === 'no') ? $header : '';  
   $processedFooter = ($footerEnabled === 'no') ? $footer : '';  
   return trim("$processedHeader\n\n$message\n\n$processedFooter");  
}  
  
// Function to handle a message  
function handleMessage($message) {  
   $chatId = $message['chat']['id'];  
   $text = $message['text'] ?? '';  
   $caption = $message['caption'] ?? '';  
   $messageId = $message['message_id'] ?? '';  
   if (preg_match('/https?:\/\/[^\s]+/i', $text) || preg_match('/https?:\/\/[^\s]+/i', $caption)) {  
      $processedText = processText($text, $chatId);  
      $processedMessage = addHeaderAndFooter($processedText, $chatId);  
      if (!empty($caption)) {  
        $processedCaption = processText($caption, $chatId);  
        $processedCaptionWithHeaderFooter = addHeaderAndFooter($processedCaption, $chatId);  
        if (isset($message['photo'])) {  
           sendMedia($chatId, 'Photo', end($message['photo'])['file_id'], $processedCaptionWithHeaderFooter, $messageId);  
        } elseif (isset($message['video'])) {  
           sendMedia($chatId, 'Video', $message['video']['file_id'], $processedCaptionWithHeaderFooter, $messageId);  
        } else {  
           sendMessage($chatId, $processedCaptionWithHeaderFooter, $messageId);  
        }  
      } else {  
        sendMessage($chatId, $processedMessage, $messageId);  
      }  
   }  
}  
  
// Function to handle a callback query  
function handleCallbackQuery($callbackQuery) {  
   $callbackId = $callbackQuery['id'];  
   $chatId = $callbackQuery['message']['chat']['id'];  
   $data = $callbackQuery['data'];  
   if ($data === 'help') {  
      sendMessage($chatId, "Here is your info");  
   }  
   answerCallbackQuery($callbackId);  
}  
  
// Function to answer a callback query  
function answerCallbackQuery($callbackId) {  
   global $telegramBotToken;  
   $url = "https://api.telegram.org/bot$telegramBotToken/answerCallbackQuery";  
   $data = [  
      'callback_query_id' => $callbackId  
   ];  
   file_get_contents($url . '?' . http_build_query($data));  
}  
  
// Function to handle an update  
function handleUpdate($update) {  
   if (isset($update['message'])) {  
      handleMessage($update['message']);  
   } elseif (isset($update['callback_query'])) {  
      handleCallbackQuery($update['callback_query']);  
   }  
}  
  
// Function to send a photo with caption and inline keyboard  
function sendPhotoWithKeyboard($chatId, $photoUrl, $caption, $keyboard) {  
   global $telegramBotToken;  
   $sendPhotoUrl = "https://api.telegram.org/bot$telegramBotToken/sendPhoto";  
   $photoData = [  
      'chat_id' => $chatId,  
      'photo' => $photoUrl,  
      'caption' => $caption,  
      'parse_mode' => 'Markdown',  
      'reply_markup' => json_encode($keyboard)  
   ];  
   file_get_contents($sendPhotoUrl . '?' . http_build_query($photoData));  
}  
  
// Function to send media  
function sendMedia($chatId, $mediaType, $mediaId, $caption, $replyToMessageId) {  
   global $telegramBotToken;  
   $sendMediaUrl = "https://api.telegram.org/bot$telegramBotToken/send$mediaType";  
   $mediaData = [  
      'chat_id' => $chatId,  
      $mediaType => $mediaId,  
      'caption' => $caption,  
      'parse_mode' => 'Markdown',  
      'reply_to_message_id' => $replyToMessageId  
   ];  
   file_get_contents($sendMediaUrl . '?' . http_build_query($mediaData));  
}  
  
// Command functions  
function startCommand($chatId, $firstName, $lastName) {  
   global $telegramBotToken;  
   $fullName = trim("$firstName $lastName");  
   $caption = "» *ʜᴇʟʟᴏ $fullName!* 😊\n\n"  
         . "» *ɪ ᴀᴍ TʜᴜɴᴅᴇʀLɪɴᴋs ᴜʀʟ ꜱʜᴏʀᴛᴇɴᴇʀ ʙᴏᴛ.*\n\n"  
         . "» *ɪ ᴄᴀɴ ꜱʜᴏʀᴛᴇɴ ᴀʟʟ ᴛʏᴘᴇꜱ ᴏғ ʟᴏɴɢ ʟɪɴᴋꜱ ᴅɪʀᴇᴄᴛʟʏ ꜰʀᴏᴍ ʏᴏᴜʀ TʜᴜɴᴅᴇʀLɪɴᴋs ᴀᴄᴄᴏᴜɴᴛ.*\n\n"  
         . "» *ʟɪɴᴋ ʏᴏᴜʀ TʜᴜɴᴅᴇʀLɪɴᴋs ᴀᴄᴄᴏᴜɴᴛ ᴠɪᴀ /ᴀᴘɪ [ʏᴏᴜʀ_ᴀᴘɪ_ᴋᴇʏ] ᴀɴᴅ ꜱᴛᴀʀᴛ ᴜꜱɪɴɢ ᴛʜɪꜱ ʙᴏᴛ.*\n\n"  
         . "» *ᴍᴀɪɴᴛᴀɪɴᴇᴅ ʙʏ*: [ Pʀᴀᴊᴡᴀʟ 🌟](https://t.me/thunderlinkshelp)\n\n"  
         . "» *ᴛᴇʟᴇɢʀᴀᴍ ᴄʜᴀɴɴᴇʟ*: [TʜᴜɴᴅᴇʀLɪɴᴋs](https://t.me/thunderlinks) ";  
   $profilePictureUrl = 'https://retrivedmodss.neocities.org/Picsart_24-11-20_14-57-32-097.jpg';  
   $keyboard = [  
      'inline_keyboard' => [  
        [  
           ['text' => 'ᴡᴇʙꜱɪᴛᴇ メ', 'url' => 'https://thunderlinks.site'],  
           ['text' => 'ᴜᴘᴅᴀᴛᴇꜱ メ​', 'url' => 'https://t.me/thunderlinks'],  
        ],  
        [  
           ['text' => '​ꜱᴜᴘᴘᴏʀᴛ​ メ', 'url' => 'https://t.me/thunderlinkschat_bot'],  
           ['text' => 'ᴘᴀʏᴏᴜᴛ ʀᴀᴛᴇꜱ​ メ', 'url' => 'https://thunderlinks.site/payout-rates'],  
        ],  
        [  
           ['text' => 'ʏᴏᴜʀ ᴀᴘɪ メ', 'url' => 'https://thunderlinks.site/member/tools/bookmarklet'],  
           ['text' => 'ᴅᴇᴠᴇʟᴏᴘᴇʀ メ', 'url' => 'https://t.me/thunderlinkshelp'],  
        ],  
      ]  
   ];  
   sendPhotoWithKeyboard($chatId, $profilePictureUrl, $caption, $keyboard);  
}  
  
function linksCommand($chatId) {  
   global $shortenedLinks;  
   if (isset($shortenedLinks[$chatId]) && !empty($shortenedLinks[$chatId])) {  
      $links = implode("\n", $shortenedLinks[$chatId]);  
      sendMessage($chatId, "ʜᴇʀᴇ ᴀʀᴇ ʏᴏᴜʀ ꜱʜᴏʀᴛᴇɴᴇᴅ ʟɪɴᴋꜱ 🔗:\n$links");  
   } else {  
      sendMessage($chatId, "ʏᴏᴜ ʜᴀᴠᴇ ɴᴏ ꜱʜᴏʀᴛᴇɴᴇᴅ ʟɪɴᴋꜱ ⛔.");  
   }  
}  
  
function totalUsersCommand($chatId) {  
   global $apiKeys;  
   $totalUsers = count($apiKeys);  
   sendMessage($chatId, "ᴛᴏᴛᴀʟ ɴᴜᴍʙᴇʀ ᴏꜰ ᴜꜱᴇʀꜱ: $totalUsers");  
}  
  
function aboutCommand($chatId) {  
   $message = "┏━━━━【 [TʜᴜɴᴅᴇʀLɪɴᴋs ʙᴏᴛ ᴀʙᴏᴜᴛ](https://t.me/thunderlinks)】━━━━✗\n"  
         . "┃ 🌟 ᴍᴇʜ ɴᴀᴍᴇ : [TʜᴜɴᴅᴇʀLɪɴᴋs ʙᴏᴛ](https://t.me/ThunderLinks_bot)\n"  
         . "┃ 👤 ᴏᴡɴᴇʀ : [Pʀᴀᴊᴡᴀʟ](https://t.me/thunderlinkshelp)\n"  
         . "┃ 📅 ᴠᴇʀsɪᴏɴ : [v1.0](https://t.me/ThunderLinks_bot)\n"  
         . "┗━━━━━━━━━━━━━━━━━━━✗";  
   sendMessage($chatId, $message);  
}  
  
function botStatusCommand($chatId) {  
   sendMessage($chatId, "ᴛʜᴇ ʙᴏᴛ ɪꜱ ᴄᴜʀʀᴇɴᴛʟʏ ᴏɴʟɪɴᴇ ᴀɴᴅ ᴡᴏʀᴋɪɴɢ ꜰɪɴᴇ! ✔");  
}  
  
function helpCommand($chatId) {  
   $helpMessage = "🧈ᴀᴠᴀɪʟᴀʙʟᴇ ᴄᴏᴍᴍᴀɴᴅꜱ:\n"  
            . "/links - ꜱʜᴏᴡ ʏᴏᴜʀ ꜱʜᴏʀᴛᴇɴᴇᴅ ʟɪɴᴋꜱ\n"  
            . "/totalusers - ꜱʜᴏᴡ ᴛᴏᴛᴀʟ ɴᴜᴍʙᴇʀ ᴏꜰ ᴜꜱᴇʀꜱ\n"  
            . "/botstatus - ꜱʜᴏᴡ ʙᴏᴛ ꜱᴛᴀᴛᴜꜱ\n"  
            . "/api [API_KEY] - ꜱᴇᴛ ʏᴏᴜʀ ɢᴋʟɪɴᴋꜱ ᴀᴘɪ ᴋᴇʏ\n"  
            . "/header [TEXT] - ꜱᴇᴛ ʜᴇᴀᴅᴇʀ ꜰᴏʀ ʏᴏᴜʀ ʟɪɴᴋꜱ\n"  
            . "/footer [TEXT] - ꜱᴇᴛ ꜰᴏᴏᴛᴇʀ ꜰᴏʀ ʏᴏᴜʀ ʟɪɴᴋꜱ\n"  
            . "/offheader - ᴛᴜʀɴ ᴏꜰꜰ ʜᴇᴀᴅᴇʀ\n"  
            . "/offfooter - ᴛᴜʀɴ ᴏꜰꜰ ꜰᴏᴏᴛᴇʀ\n"  
            . "/start - ꜱᴛᴀʀᴛ ᴛʜᴇ ʙᴏᴛ\n";  
   sendMessage($chatId, $helpMessage);  
}  
  
function setApiKeyCommand($chatId, $text) {  
   global $apiKeys;  
   $apiKey = trim(substr($text, strlen('/api')));  
   if (!empty($apiKey)) {  
      $apiKeys[$chatId] = $apiKey;  
      saveApiKeys();  
      sendMessage($chatId, "ʟᴏɢɪɴ ꜱᴜᴄᴄᴇꜱꜱꜰᴜʟ :ʏᴏᴜʀ ᴀᴄᴄᴏᴜɴᴛ ɪꜱ ᴄᴏɴɴᴇᴄᴛᴇᴅ ᴛᴏ ᴛʜɪꜱ ʙᴏᴛ 🛑📝.");  
   } else {  
      sendMessage($chatId, "ᴘʟᴇᴀꜱᴇ ᴘʀᴏᴠɪᴅᴇ ᴀ ᴠᴀʟɪᴅ ᴀᴘɪ ᴋᴇʏ 💻.");  
   }  
}  
  
function myApiCommand($chatId) {  
   global $apiKeys;  
   if (isset($apiKeys[$chatId])) {  
      $apiKey = $apiKeys[$chatId];  
      sendMessage($chatId, "*ʏᴏᴜʀ ᴄᴜʀʀᴇɴᴛ ᴀᴘɪ ᴋᴇʏ ɪꜱ* 🔑: `{$apiKey}`");  
   } else {  
      sendMessage($chatId, "*ʏᴏᴜ ʜᴀᴠᴇ ɴᴏᴛ ꜱᴇᴛ ᴀɴ ᴀᴘɪ ᴋᴇʏ ʏᴇᴛ. ᴘʟᴇᴀꜱᴇ ᴜꜱᴇ* /api [ʏᴏᴜʀ_ᴀᴘɪ_ᴋᴇʏ] *ᴛᴏ ꜱᴇᴛ ɪᴛ.*");  
   }  
}  
  
function setHeaderCommand($chatId, $text) {  
   global $userSettings;  
   $headerContent = trim(substr($text, strlen('/header')));  
   $userSettings[$chatId]['header'] = $headerContent;  
   saveUserSettings();  
   sendMessage($chatId, "ʜᴇᴀᴅᴇʀ ʜᴀꜱ ʙᴇᴇɴ ꜱᴇᴛ ꜱᴜᴄᴄᴇꜱꜱꜰᴜʟʟʏ.");  
}  
  
function setFooterCommand($chatId, $text) {  
   global $userSettings;  
   $footerContent = trim(substr($text, strlen('/footer')));  
   $userSettings[$chatId]['footer'] = $footerContent;  
   saveUserSettings();  
   sendMessage($chatId, "ꜰᴏᴏᴛᴇʀ ʜᴀꜱ ʙᴇᴇɴ ꜱᴇᴛ ꜱᴜᴄᴄᴇꜱꜱꜰᴜʟʟʏ.");  
}  
  
function offHeaderCommand($chatId) {  
   global $userSettings;  
   $userSettings[$chatId]['off_header'] = 'yes';  
   saveUserSettings();  
   sendMessage($chatId, "ʜᴇᴀᴅᴇʀ ʜᴀꜱ ʙᴇᴇɴ ᴛᴜʀɴᴇᴅ ᴏꜰꜰ.");  
}  
  
function offFooterCommand($chatId) {  
   global $userSettings;  
   $userSettings[$chatId]['off_footer'] = 'yes';  
   saveUserSettings();  
   sendMessage($chatId, "ꜰᴏᴏᴛᴇʀ ʜᴀꜱ ʙᴇᴇɴ ᴛᴜʀɴᴇᴅ ᴏꜰꜰ.");  
}  
  
function offHeaderFooterCommand($chatId) {  
   global $userSettings;  
   $userSettings[$chatId]['off_header'] = 'yes';  
   $userSettings[$chatId]['off_footer'] = 'yes';  
   saveUserSettings();  
   sendMessage($chatId, "ʙᴏᴛʜ ʜᴇᴀᴅᴇʀ ᴀɴᴅ ꜰᴏᴏᴛᴇʀ ʜᴀᴠᴇ ʙᴇᴇɴ ᴛᴜʀɴᴇᴅ ᴏꜰꜰ.");  
}  
  
// Main execution  
$content = file_get_contents("php://input");  
$update = json_decode($content, true);  
  
if (isset($update['message'])) {  
   handleMessage($update['message']);  
   $chatId = $update['message']['chat']['id'];  
   $text = $update['message']['text'] ?? '';  
  
   // Commands handling  
   if (preg_match('/\/start/', $text)) {  
      startCommand($chatId, $update['message']['from']['first_name'], $update['message']['from']['last_name']);  
   } elseif (preg_match('/^\/api /', $text)) {  
      setApiKeyCommand($chatId, $text);  
   } elseif (preg_match('/^\/header /', $text)) {  
      setHeaderCommand($chatId, $text);  
   } elseif (preg_match('/^\/footer /', $text)) {  
      setFooterCommand($chatId, $text);  
   } elseif (preg_match('/\/off_header/', $text)) {  
      offHeaderCommand($chatId);  
   } elseif (preg_match('/\/off_footer/', $text)) {  
      offFooterCommand($chatId);  
   } elseif (preg_match('/\/off_header_footer/', $text)) {  
      offHeaderFooterCommand($chatId);  
   }  
  
   if (preg_match('/\/links/', $text)) {  
      linksCommand($chatId);  
   } elseif (preg_match('/\/totalusers/', $text)) {  
      totalUsersCommand($chatId);  
   } elseif (preg_match('/\/botstatus/', $text)) {  
      botStatusCommand($chatId);  
   } elseif (preg_match('/\/help/', $text)) {  
      helpCommand($chatId);  
   } elseif (preg_match('/\/about/', $text)) {  
      aboutCommand($chatId);  
   } elseif (preg_match('/\/myapi/', $text)) {  
      myApiCommand($chatId);  
   }  
}  
  
?>
