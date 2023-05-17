<?php
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: access");
    header("Access-Control-Allow-Methods: GET, POST");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    //db connection settings
    $servidor = "localhost"; $usuario = "root"; $password = ""; $nombreBaseDatos = "colorines";
    //enlace
    $enlace = new mysqli($servidor, $usuario, $password, $nombreBaseDatos);
    
    //Datos que se reciben por json
    $payload = json_decode(file_get_contents('php://input'));
    $codeResult = null;

    if ($_GET){

        if ( isset($_GET['login']) ) {

            $username = $payload -> username;
            $password = md5($payload -> password);

            $query = "SELECT * FROM usuarios u JOIN roles r ON u.id_rol = r.id  WHERE username = '".mysqli_real_escape_string($enlace, strtolower($username))."'";
            if ($result = mysqli_query($enlace, $query)){
                if (mysqli_num_rows($result) > 0 ){
                    $row = mysqli_fetch_assoc($result);
                    if ($row['password'] === $password){
                        $apiKey = ($row['id'].$password);
                        $apiKey = genApiKey($apiKey, $row['id'], $enlace);
                        if ( $apiKey != false ){
                            $msg = [
                                'id' => $row['id'],
                                'fullname' => $row['nombres']." ".$row['apellidos'],
                                'username' => $row['username'],
                                'email' => $row['email'],
                                'rol' => $row['rol'],
                                'apiKey' => $apiKey
                            ];
                            $codeResult = 201;
                        }else{
                            $codeResult = 100;
                            $msg = "Hubo un problema al generar la API key";
                        }
                    }else{
                        $msg = "Contraseña incorrecta";
                        $codeResult = 100;
                    }
                }else{
                    $msg = "El usuario no coincide con alguno registrado.";
                    $codeResult = 100;
                }
                $status = true;
            }else{
                $msg = "Ha ocurrido un error inesperado";
                $status = false;
                $codeResult = 500;
            }
        }

        if ( isset($_GET['register']) ) {

            //A manera practica, no se aplican mayores validaciones como mayusculas y minusculas
            $nombres = $payload -> nombres;
            $apellido = $payload -> apellido;

            //Solo en este caso, para garantizar un correcto inición de sesión y validación de usuarios existentes
            $username = strtolower($payload -> username);
            $email = $payload -> email;

            $password = md5($payload -> password);

            //verificacion dos en uno
            $query = "SELECT * FROM usuarios WHERE 
            username = '".mysqli_real_escape_string($enlace, strtolower($username))."' 
            OR email = '".mysqli_real_escape_string($enlace, $email)."' LIMIT 1";
            if ($result = mysqli_query($enlace, $query)){
                if (mysqli_num_rows($result) > 0){$status=true;$msg="El usuario o correo que ingresaste ya está registrado";$codeResult=100;}
                else {
                    $query = "INSERT INTO usuarios (`nombres`, `apellidos`, `username`, `email`, `password`) VALUES ('".mysqli_real_escape_string($enlace,$nombres)."','".mysqli_real_escape_string($enlace,$apellido)."', '".mysqli_real_escape_string($enlace,$username)."','".mysqli_real_escape_string($enlace,$email)."', '".mysqli_real_escape_string($enlace,$password)."')";

                    if (mysqli_query($enlace, $query)){
                        $id_user = mysqli_insert_id($enlace);
                        $apiKey = md5($id_user.$password);
                        
                        $apiKey = genApiKey($apiKey, $id_user, $enlace);
                        if ( $apiKey != false ) {
                            $msg = [
                                'id' => $id_user,
                                'fullname' => $nombres." ".$apellido,
                                'username' => $username,
                                'email' => $email,
                                'rol' => 1,
                                'apiKey' => $apiKey
                            ];
                            $status = true;
                            $codeResult = 201;
                        }else{
                            $msg = "Ha ocurrido un error inesperado";
                            $status = false;
                            $codeResult = 500;
                        }
                    }else{
                        $msg = "Ha ocurrido un error inesperado";
                        $status = false;
                        $codeResult = 500;
                    }
                }
            }
        }

        if( isset($_GET['usuarios']) ) {
            $query = "SELECT *, u.id AS id_user FROM usuarios u JOIN roles r ON u.id_rol = r.id LIMIT 1000";
            if($result = mysqli_query($enlace, $query)){
                $data = [];

                while($row = mysqli_fetch_assoc($result)){
                    $return = Array(
                        'id' => $row['id_user'],
                        'fullname' => $row['nombres'] . ' ' . $row['apellidos'], 
                        'username' => $row['username'], 
                        'email' => $row['email'],
                        'rol' => $row['rol']
                    );
                    array_push($data, $return);
                }

                $msg = json_encode($data);
                $status = true;
                $codeResult = 201;
            }
        }

        if( isset($_GET['productos']) ) {
            if ( isset ($_GET['active']) ) {
                $condition = "WHERE active = 1";
            }else{
                $condition = "";
            }
            $query = "SELECT * FROM articulos $condition LIMIT 1000";
            if($result = mysqli_query($enlace, $query)){
                $data = [];

                while($row = mysqli_fetch_assoc($result)){
                    $return = Array(
                        'id' => $row['id'],
                        'producto' => $row['articulo'],
                        'img' => $row['imagen'], 
                        'costo' => $row['precio'],
                        'prod_estatus' => $row['active']
                    );
                    array_push($data, $return);
                }

                $msg = json_encode($data);
                $status = true;
                $codeResult = 201;
            }
        }

        if( isset($_GET['deleteUser'] ) ) {
            $query = "SELECT * FROM apiKeys ak JOIN usuarios u ON u.id = ak.id_user WHERE ak.`key` = '".mysqli_real_escape_string($enlace, $payload -> apiKey)."' AND ak.`id_user` = '".mysqli_real_escape_string($enlace, $payload -> id_user)."' LIMIT 1";
            if ($result = mysqli_query($enlace, $query)){
                if ( mysqli_num_rows($result) > 0 ) {
                    $query = "DELETE FROM apikeys WHERE id_user = '".mysqli_real_escape_string($enlace, $payload -> del_id)."' LIMIT 1;";
                    if (mysqli_query($enlace, $query)){
                        $query = "DELETE FROM usuarios WHERE id = '".mysqli_real_escape_string($enlace, $payload -> del_id)."' LIMIT 1;";
                        if (mysqli_query($enlace, $query)) {
                            $msg = "Acción realizada";
                            $status = true;
                            $codeResult = 201; 
                        }
                    }
                }
            }
        }

        if( isset($_GET['updateUser']) ) {
            $apiKey = $payload -> apiKey;
            $query_string = "";
            if ($payload -> password != "" && $payload -> password != null){
                $password = md5($payload -> password);
                if ($query_string != ""){
                    $query_string .= ", password = '".mysqli_real_escape_string($enlace, $password)."'";
                }else{
                    $query_string .= "password = '".mysqli_real_escape_string($enlace, $password)."'";
                }
            }

            if ($payload -> rol != "" && $payload -> rol != null){
                if ($query_string != ""){
                    $query_string .= ", id_rol = '".mysqli_real_escape_string($enlace, $payload -> rol)."'";
                }else{
                    $query_string .= "id_rol = '".mysqli_real_escape_string($enlace, $payload -> rol)."'";
                }
            }

            $query = "SELECT r.rol FROM usuarios u JOIN roles r ON u.id_rol = r.id WHERE u.id = '".mysqli_real_escape_string($enlace, $payload -> id_user)."' AND rol = 'Administrador'";
            if ($result = mysqli_query($enlace, $query)){
                if (mysqli_num_rows($result) > 0 && $query_string != ""){
                    $query = "UPDATE usuarios SET $query_string WHERE id = '".mysqli_real_escape_string($enlace, $payload -> id_user)."' LIMIT 1";
                    if ( mysqli_query($enlace, $query) ) {
                        $msg = "Hecho";
                        $codeResult = 201;
                        $status = true;
                    }else{
                        $msg = "Ocurrio un error";
                        $codeResult = 100;
                        $status = true;
                    }
                }else{
                    $msg = "Ocurrio un error";
                    $codeResult = 100;
                    $status = true;
                }
            }else{
                $msg = "Ocurrio un error";
                $codeResult = 100;
                $status = true;
            }
        }

        if ( isset($_GET['addProd']) ) {
            $apiKey = $payload -> apiKey;

            //No aplico mayor validación

            if ( $payload -> img != "" ){
                $img = $payload -> img;
            }else{
                $img = "data:image/png;base64,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";
            }

            $query = "SELECT r.rol FROM usuarios u JOIN roles r ON u.id_rol = r.id WHERE u.id = '".mysqli_real_escape_string($enlace, $payload -> id_user)."' AND rol = 'Administrador'";
            if ($result = mysqli_query($enlace, $query)){
                if (mysqli_num_rows($result) > 0){
                    $query = "INSERT INTO articulos (`articulo`,`imagen`,`precio`,`active`) VALUES ('".mysqli_real_escape_string($enlace, $payload -> producto)."','".mysqli_real_escape_string($enlace, $payload -> img)."','".mysqli_real_escape_string($enlace, $payload -> costo)."','".mysqli_real_escape_string($enlace, $payload -> prod_estatus)."')";
                    if ( mysqli_query($enlace, $query) ) {
                        $msg = "Producto guardado";
                        $codeResult = 201;
                        $status = true;
                    }else{
                        $msg = "Ocurrio un error";
                        $codeResult = 100;
                        $status = true;
                    }
                }else{
                    $msg = "Ocurrio un error";
                    $codeResult = 100;
                    $status = true;
                }
            }else{
                $msg = "Ocurrio un error";
                $codeResult = 100;
                $status = true;
            }
        }

        if ( isset($_GET['editProd']) ) {
            $apiKey = $payload -> apiKey;
            $query = "SELECT r.rol FROM usuarios u JOIN roles r ON u.id_rol = r.id WHERE u.id = '".mysqli_real_escape_string($enlace, $payload -> id_user)."' AND rol = 'Administrador'";
            if ($result = mysqli_query($enlace, $query)){
                if (mysqli_num_rows($result) > 0 ){
                    $query_string = "";
                    if ($payload -> img != ""){
                        $query_string .= "img = '".mysqli_real_escape_string($enlace, $payload -> img)."'";
                    }
                    if ( $payload -> prod_estatus != ""){
                        $prod_estatus = $payload -> prod_estatus;
                    }else{ $prod_estatus = 1; }
                    if ($query_string != ""){
                        $query_string .= ",";
                    }

                    $query_string .= "articulo = '".mysqli_real_escape_string($enlace, $payload -> producto)."', precio= '".mysqli_real_escape_string($enlace, $payload -> costo)."', active = '".mysqli_real_escape_string($enlace, $prod_estatus)."'";
                    $query = "UPDATE articulos SET $query_string WHERE id = '".mysqli_real_escape_string($enlace, $payload -> id)."' LIMIT 1";
                    if ( mysqli_query($enlace, $query) ) {
                        $msg = "Hecho";
                        $codeResult = 201;
                        $status = true;
                    }else{
                        $msg = "Ocurrio un error";
                        $codeResult = 100;
                        $status = true;
                    }
                }else{
                    $msg = "Ocurrio un error";
                    $codeResult = 100;
                    $status = true;
                }
            }else{
                $msg = "Ocurrio un error";
                $codeResult = 100;
                $status = true;
            }
        }

        if( isset($_GET['deleteProd'] ) ) {
            $query = "SELECT * FROM apiKeys ak JOIN usuarios u ON u.id = ak.id_user WHERE ak.`key` = '".mysqli_real_escape_string($enlace, $payload -> apiKey)."' AND ak.`id_user` = '".mysqli_real_escape_string($enlace, $payload -> id_user)."' LIMIT 1";
            if ($result = mysqli_query($enlace, $query)){
                if ( mysqli_num_rows($result) > 0 ) {
                    $query = "DELETE FROM articulos WHERE id = '".mysqli_real_escape_string($enlace, $payload -> del_id)."' LIMIT 1;";
                    if (mysqli_query($enlace, $query)){
                        $msg = "Acción realizada";
                        $status = true;
                        $codeResult = 201; 
                    }
                }
            }
        }

        if ( isset($_GET['logOut']) ) {
            $query = "DELETE FROM apikeys WHERE id_user = '".mysqli_real_escape_string($enlace, $payload -> id_user)."'";
            if (mysqli_query($enlace, $query)){
                $msg = "Acción realizada";
                $status = true;
                $codeResult = 201; 
            }
        }
        
        $response = [
            "ok" => $status,
            "response" => $codeResult,
            "message" => $msg
        ];

        echo json_encode($response);
    }else{
        $response = [
            "ok" => true,
            "message" => "API COLORINES V.1.0.0"
        ];

        echo json_encode($response);
    }

    function genApiKey($apiKey, $id_user){
        //db connection settings
        $servidor = "localhost"; $usuario = "root"; $password = ""; $nombreBaseDatos = "colorines";
        //enlace
        $enlace = new mysqli($servidor, $usuario, $password, $nombreBaseDatos);

        $query = "INSERT INTO apikeys (`key`, `id_user`) VALUES ('".mysqli_real_escape_string($enlace, $apiKey)."','".mysqli_real_escape_string($enlace, $id_user)."');";
        if (mysqli_query($enlace, $query)) {
            $query = "SELECT created FROM apikeys WHERE `key` = '".mysqli_real_escape_string($enlace, $apiKey)."' LIMIT 1";
            if ( $r = mysqli_query($enlace, $query) ) {
                $tr = mysqli_fetch_assoc($r);
                $query = "UPDATE apikeys SET `key`= '".mysqli_real_escape_string($enlace, md5($apiKey.$tr['created']))."' WHERE `id_user`= '".mysqli_real_escape_string($enlace, $id_user)."'";
                if (mysqli_query($enlace, $query)){
                    return md5($apiKey.$tr['created']);
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
?>