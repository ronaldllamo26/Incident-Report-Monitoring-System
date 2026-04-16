<?php
// Scratch script to test available Gemini models for the user's key
require_once __DIR__ . '/../config/ai_config.php';

$apiKey = GEMINI_API_KEY;

// 1. Get the list of models
$listUrl = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
$chList = curl_init($listUrl);
curl_setopt($chList, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chList, CURLOPT_SSL_VERIFYPEER, false);
$listResponse = curl_exec($chList);
curl_close($chList);

$listData = json_decode($listResponse, true);
$models = $listData['models'] ?? [];

echo "<h1>AI Auto-Discovery Test</h1>";
echo "Found " . count($models) . " models in your account.<br><br>";

$workingModel = null;
$errors = [];

// 2. Try each model until one works
foreach ($models as $m) {
    if (!in_array('generateContent', $m['supportedGenerationMethods'])) continue;
    
    $modelName = $m['name'];
    $genUrl = "https://generativelanguage.googleapis.com/v1beta/{$modelName}:generateContent?key=" . $apiKey;
    
    $data = ["contents" => [["parts" => [["text" => "Hi"]]]]];
    
    $chGen = curl_init($genUrl);
    curl_setopt($chGen, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chGen, CURLOPT_POST, true);
    curl_setopt($chGen, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($chGen, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($chGen, CURLOPT_SSL_VERIFYPEER, false);
    
    $res = curl_exec($chGen);
    $http = curl_getinfo($chGen, CURLINFO_HTTP_CODE);
    curl_close($chGen);
    
    if ($http === 200) {
        $workingModel = $modelName;
        break;
    } else {
        $errors[] = "$modelName (HTTP $http): " . $res;
    }
}

if ($workingModel) {
    echo "<h2 style='color:green'>SUCCESS! Found working model: $workingModel</h2>";
    echo "Updating system logic now...";
} else {
    echo "<h2 style='color:red'>FAILURE: No working models found.</h2>";
    echo "<h3>Error Log:</h3><pre>" . print_r($errors, true) . "</pre>";
}
?>
