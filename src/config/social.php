<?php

return array(
    //create account https://vk.com/editapp?act=create
    'vk' => array(
        "status" => true,
        "api_id" => "5009923",
        "secret_key" => "PdTvngSv7pXK5VHHupZj",
        "redirect_url" => "",
        "remember" => false,
        "foto" => true
    ),
    //create account https://developers.facebook.com/quickstarts/?platform=web
    'fb' => array(
        "status"=>true,
        "api_id"=>"885147974889661",
        "secret_key" => "e7a172cdfaede22cf0b26b40816e20c8",
        "redirect_url" => "",
        "remember" => false
    ),
    //create account https://console.developers.google.com/project
    'google'  => array(
        "status" => true,
        "api_id" => "916040842645-pp67lbsjpttg3ojnqmehg4eaiu6r12m6.apps.googleusercontent.com",
        "secret_key" => "lq69x7jO0MSN-ih6IMyHCNXM",
        "redirect_oauth2callback" => "http://lara5.vis-design.com/auth_soc/oauth2callback",
        "redirect_url" => "",
        "remember" => false
    ),
);
