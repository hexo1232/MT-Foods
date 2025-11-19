<?php require_once __DIR__ . '/../src/helpers.php'; ?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Redefinir Palavra-passe</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        form {
            max-width: 400px;
            margin: 60px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #aaa;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #f1bf1b;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background-color:  #b68c05ff;
        }
    </style>
</head>
<body>



<form method="POST" action="send_reset.php">
    <h2>Esqueceu a palavra-passe?</h2>
    <label for="email">E-mail:</label><br>
    <input type="email" name="email" required>
    <button type="submit">Enviar link de redefinição</button>
</form>
    </body>
