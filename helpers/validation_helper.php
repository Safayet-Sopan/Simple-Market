<?php
function cleanInput($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}

function validate_name($value)
{
    if ($value === '') {
        return "Name is required";
    }
    if (!preg_match("/^[a-zA-Z-' ]*$/", $value)) {
        return "Only letters and white spaces are allowed.";
    }
    return "";
}

function validate_email_format($value)
{
    if ($value === '') {
        return "Email cannot be empty";
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format";
    }
    return "";
}

function validate_phone($value)
{
    if ($value === '') {
        return "";
    }
    if (!preg_match("/^[0-9+\- ]{7,20}$/", $value)) {
        return "Invalid phone number";
    }
    return "";
}

function validate_password($value)
{
    if ($value === '') {
        return "Password cannot be empty";
    }
    if (strlen($value) < 8) {
        return "Password must be at least 8 characters";
    }
    if (!preg_match("/[A-Za-z]/", $value) || !preg_match("/[0-9]/", $value)) {
        return "Password must contain at least one letter and one number";
    }
    return "";
}

function validate_rating($value)
{
    if ($value === '') {
        return "Pick a rating";
    }
    if (!ctype_digit((string)$value) || (int)$value < 1 || (int)$value > 5) {
        return "Rating must be between 1 and 5";
    }
    return "";
}

function is_id($value)
{
    return $value !== null && $value !== '' && ctype_digit((string)$value) && (int)$value > 0;
}

function money($amount)
{
    return '৳' . number_format((float)$amount, 2);
}
