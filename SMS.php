<?php
// ===============================
// TERMII BULK SMS HANDLER
// ===============================
if(isset($_POST['action']) && $_POST['action'] == "send_sms"){

    $apiKey = "TLYzxQoUbLanECfiqIBtsafEbZAFsVLFWBXkjAwishDHiqFdGRYnAJNrLjHPKA";
    $sender = "WQS";

    $numbers = $_POST['numbers'];
    $message = $_POST['message'];

    if(empty($numbers) || empty($message)){
        echo json_encode(["status"=>"error","msg"=>"Phone numbers and message required"]);
        exit;
    }

    $phones = implode(",", $numbers);

    $data = [
        "to" => $phones,
        "from" => $sender,
        "sms" => $message,
        "type" => "plain",
        "channel" => "generic",
        "api_key" => $apiKey
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.ng.termii.com/api/sms/send/bulk",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    echo $response;
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Termii Bulk SMS Sender</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
background:#f4f6f9;
}

.card{
border-radius:12px;
box-shadow:0 0 20px rgba(0,0,0,0.08);
}

.phone-row{
margin-bottom:10px;
}

.add-btn{
font-size:18px;
}

.remove-btn{
font-size:18px;
}

</style>

</head>

<body>

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-md-7">

<div class="card p-4">

<h4 class="mb-4 text-center">Send Bulk SMS (Termii)</h4>

<div id="result"></div>

<form id="smsForm">

<div id="phoneContainer">

<div class="row phone-row">

<div class="col-10">
<input type="text" name="numbers[]" class="form-control" placeholder="Phone Number e.g 2348012345678" required>
</div>

<div class="col-2">
<button type="button" class="btn btn-success add-btn w-100">+</button>
</div>

</div>

</div>

<div class="mb-3 mt-3">

<textarea name="message" class="form-control" rows="4" placeholder="Enter SMS Message" required></textarea>

</div>

<button class="btn btn-primary w-100" type="submit">Send SMS</button>

</form>

</div>

</div>

</div>

</div>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>

$(document).ready(function(){

// ADD NUMBER FIELD
$(document).on("click",".add-btn",function(){

let html = `
<div class="row phone-row">

<div class="col-10">
<input type="text" name="numbers[]" class="form-control" placeholder="Phone Number">
</div>

<div class="col-2">
<button type="button" class="btn btn-danger remove-btn w-100">-</button>
</div>

</div>
`;

$("#phoneContainer").append(html);

});


// REMOVE NUMBER FIELD
$(document).on("click",".remove-btn",function(){

$(this).closest(".phone-row").remove();

});



// SEND SMS USING AJAX
$("#smsForm").submit(function(e){

e.preventDefault();

let formData = $(this).serialize() + "&action=send_sms";

$.ajax({

url:"",
type:"POST",
data:formData,
beforeSend:function(){

$("#result").html(`<div class="alert alert-info">Sending SMS...</div>`);

},
success:function(res){

$("#result").html(`<div class="alert alert-success">SMS Sent Successfully</div>`);

},
error:function(){

$("#result").html(`<div class="alert alert-danger">Error Sending SMS</div>`);

}

});

});

});

</script>

</body>
</html>