<?php
    // Include dependencies
    include "include/app.php";
    include "config.php";

    // Helper function to generate a v4 UUID
    function gen_uuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    // Convert base64 string to image file
    function base64_to_image($base64_string, $output_file) {
        if(true){ //base64_get_extension($base64_string)
            $ifp = fopen($output_file, "wb");
            $data = explode(',', $base64_string);
            fwrite($ifp, base64_decode($data[1]));
            fclose($ifp);
            return true;
        } else {
            return false;
        }
    }

    // Create a new app instance
    $app = new App();

    // Upload image and score it
    $app->add("upload_image", function(){
        $targetDir = "images/";
        $targetFile = gen_uuid();
        if(base64_to_image($_POST["file"], $targetDir . $targetFile)){
            $res = shell_exec($config["python_path"] . " ../checking.py 'http://52.229.117.35/microwave-time/api/" . $targetDir . $targetFile . "' 2>&1");
            //echo $res;
            echo $targetFile;
            // Parse python output to json object
            $res = explode("\n", $res);
            $json = array();
            // Check if food is microwaveable
            if($res[0] == "0"){
                $json["warning"] = false;
            } else {
                $json["warning"] = true;
            }
            $json["score"] = array();
            for ($i = 1; $i <= max(array_keys($res)); $i++){
                if($res[$i] == ""){
                    continue;
                }
                $arr = explode("/",$res[$i]);
                array_push($json["score"], $arr);
            }
            // Calculate cook time and calorie count
            $food_cook_times = json_decode(file_get_contents("food_cook_time.json"),true);
            $food_cal = json_decode(file_get_contents("food_cal.json"),true);
            $total_time = 0;
            $total_cal = 0;
            foreach($json["score"] as $food){
                if(empty($food_cook_times[$food[0]])){
                    $food_cook_times[$food[0]] = 90;
                    $total_time += 90;
                } else {
                    $total_time += $food_cook_times[$food[0]] * $food[1];
                }
                if(empty($food_cal[$food[0]])){
                    $total_cal += 500;
                } else {
                    $total_cal += $food_cal[$food[0]] * $food[1];
                }
            }
            file_put_contents("food_cook_time.json", json_encode($food_cook_times));
            // Calculate average
            $json["total_cook_time"] = floor($total_time / (max(array_keys($json["score"])) + 1));
            $json["total_cal"] = floor($total_cal / (max(array_keys($json["score"])) + 1));
            // Save json to file
            file_put_contents($targetDir . $targetFile . ".json", json_encode($json));
        } else {
            echo "500";
        }
    });

    $app->add("train", function(){
        $food_cook_times = json_decode(file_get_contents("food_cook_time.json"),true);
        $image_uuid = $_GET["uuid"];
        $train_method = $_GET["method"]; //up or down
        $image_meta = json_decode(file_get_contents("images/" . $image_uuid . ".json"), true);
        $image_score = $image_meta["score"];
        foreach($image_score as $element){
            if($train_method === "up"){
                $food_cook_times[$element[0]] -= $element[1] * 3;
            } else {
                $food_cook_times[$element[0]] += $element[1] * 3;
            }
        }
        file_put_contents("food_cook_time.json", json_encode($food_cook_times));
    });

    // Score an image, output cook time
    $app->add("score_image", function(){
            $res = shell_exec($config["python_path"] . " ../checking.py '" . $_GET["url"] . "' 2>&1");
            // Parse python output to json object
            $res = explode("\n", $res);
            $json = array();
            $json["score"] = array();
            for ($i = 1; $i <= max(array_keys($res)); $i++){
                if($res[$i] == ""){
                    continue;
                }
                $arr = explode("/",$res[$i]);
                array_push($json["score"], $arr);
            }
            $food_cook_times = json_decode(file_get_contents("food_cook_time.json"),true);
            $food_cal = json_decode(file_get_contents("food_cal.json"),true);
            $total_time = 0;
            $total_cal = 0;
            foreach($json["score"] as $food){
                if(empty($food_cook_times[$food[0]])){
                    $total_time += 90;
                } else {
                    $total_time += $food_cook_times[$food[0]] * $food[1];
                }
                if(empty($food_cal[$food[0]])){
                    $total_cal += 500;
                } else {
                    $total_cal += $food_cal[$food[0]] * $food[1];
                }
            }
            $json["total_cook_time"] = floor($total_time / (max(array_keys($json["score"])) + 1));
            $json["total_cal"] = floor($total_cal / (max(array_keys($json["score"])) + 1));
            echo $json["total_cook_time"];
    });

    // Check if food is microwaveable
    $app->add("microwaveable", function(){
        $res = shell_exec($config["python_path"] . " ../checking.py '" . $_GET["url"] . "' 2>&1");
        // Parse python output to json object
        $res = explode("\n", $res);
        $json = array();
        if($res[0] == "0"){
            echo 0;
        } else {
            echo 1;
        }
    });

    $app->route($_GET["action"]);
?>
