
<?php 

set_time_limit(0);
function array_to_csv_download($array, $filename = "export.csv", $delimiter=",") {
    // ob_start();
    header("Content-type: text/csv"); // application/csv
    header("Content-Disposition: attachment; filename=".$filename );
    header("Pragma: no-cache");
    header("Expires: 0");
    // clean output buffer
    //ob_end_clean();
    // open the "output" stream
    // see http://www.php.net/manual/en/wrappers.php.php#refsect2-wrappers.php-unknown-unknown-unknown-descriptioq
    $f = fopen('php://output', 'w');
    //$f = fopen('php://memory', 'w'); 
    fputcsv($f, ['Full Points', 'Criteria ID', 'Criteria Title',' Criteria Long Description','Student Counts', 'Exceeds', 'Meets', 'Below', 'Didnt mark', 'Total Counts'], $delimiter);
    foreach ($array as $line) {
        fputcsv($f, $line, $delimiter);
    }
    //fseek($f, 0);
    //fpassthru($f);
    fclose($f);
    // flush buffer
    //ob_flush();
    exit();
}  

function cmp($a, $b){
    if($a[2] == $b[2]){
        return 0;
    }
    return ($a[2] > $b[2]) ? -1 : 1;
} 

$flag = true;
$course_id = "";
$token = "";
$criteria_ids = [];
$assignment_id = "";
$selected_assign = null;
$assignment_list = [];
$criteria_list = [];
$submission_list = [];

function callCurl($url){
    try{
        //Initiate the curl to call the API from the server. API URL can be changed in the $url variable.
        //Initializing a new cURL session and fetching a web page 
        $ch = curl_init();	//Step 1: Curl Handle ch variable initialization 
        // step 2 
        // 2.1 Set the url
        curl_setopt($ch, CURLOPT_URL,$url); //
        //curl_setopt($ch, CURLOPT_PROXY,             "127.0.0.1");
        //curl_setopt($ch, CURLOPT_PROXYPORT,         "3213");
        // 2.2Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // FALSE to stop cURL from verifying the peer's certificate.
        // 2.3 Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //TRUE to return the transfer as a string of the return value of curl_exec() instead of outputting it out directly. 
        // Step 3 Execute request and fetch the responce. Check for errors 
        $result_temp = curl_exec($ch); // as RETURNTRANSFER set to true, data returned stored in result_temp variable
        curl_close($ch);
        $result = [];
        if(!isset(json_decode($result_temp,true)["errors"])) {
            $result = json_decode($result_temp, true); // Takes a JSON 
        } 
        return $result;        
        // Step 4 Close and free up the curl handle
    } catch(Exception $e) {
        print_r("Caught ". json_encode($e));
        throw Exception('Some error was happened');
    }
}

if($_REQUEST){
	$course_id = $_REQUEST['course_id'];
	$token = $_REQUEST['access_token'];
    if( isset($_REQUEST["assignment_id"]) ) {
        $assignment_id = $_REQUEST["assignment_id"];   
    }
    if( isset($_REQUEST["criteria_ids"]) ) {
        $criteria_ids = json_decode($_REQUEST["criteria_ids"]);   
    }
    if($course_id != '' and $token != ""){
    
        $url = "https://rmit.instructure.com/api/v1/courses/".trim($course_id)."/assignments?per_page=100&access_token=" . $token; 
        $assignment_list = callCurl($url);
        
        if($flag and $assignment_id != ""){
            $url = "https://rmit.instructure.com/api/v1/courses/" . trim($course_id) . "/assignments/".trim($assignment_id)."?per_page=100&access_token=" . $token; 
            $selected_assign = callCurl($url);
            if(isset($selected_assign["rubric"]) and $selected_assign["rubric"] != null)
                $criteria_list = $selected_assign["rubric"]; 
        }

        if(count($criteria_ids)>0){
        
            $page_number = 1;
            $data = array();
            $student_data = array();
            for($j=0;$j<count($criteria_ids);$j++){
                $cri_id = $criteria_ids[$j];
                $selected_criteria = null;
                for($k=0;$k<count($criteria_list);$k++){
                    if($criteria_list[$k]["id"] == $cri_id){
                        $selected_criteria = $criteria_list[$k];
                    }
                }
                if( $selected_criteria != null ){
                    $data[$cri_id] = array($selected_criteria["points"],$cri_id, $selected_criteria["description"], $selected_criteria["long_description"], 0,0,0,0,0,0);
                    // criteria full points , criteria description, submit counts, exceeds (80%~above) , meets(50%~79%), belows (49%~below) , didnt mark, total counts
                    
                    $student_data[$cri_id] = array();
                }
            }
            
            while(true) {
            
                $url = "https://rmit.instructure.com/api/v1/courses/" . trim($course_id) . "/assignments/".trim($assignment_id)."/submissions?page=".$page_number."&per_page=100&include[]=rubric_assessment&access_token=" . $token; 
                $submission_list = callCurl($url);
                if(count($submission_list)>0) {
                    for($j=0;$j<count($criteria_ids);$j++){
                        $cri_id = $criteria_ids[$j];                       
                        if( isset($data[$cri_id]) and $data[$cri_id] != null){
                            $criteria_points = $data[$cri_id][0];
                            for($m = 0 ; $m < count($submission_list); $m++){
                                $pt = null;
                                if(isset($submission_list[$m]["rubric_assessment"][$cri_id]) and $submission_list[$m]["rubric_assessment"][$cri_id] != null){
                                    if(isset($submission_list[$m]["rubric_assessment"][$cri_id]["points"]) and $submission_list[$m]["rubric_assessment"][$cri_id]["points"] != null){
                                        $pt = $submission_list[$m]["rubric_assessment"][$cri_id]["points"];
                                        $data[$cri_id][4] += 1;
                                        $percentage = $pt/$criteria_points * 100;
                                        if( $percentage >= 80){
                                            $data[$cri_id][5] += 1;
                                        } else if($percentage >= 50){
                                            $data[$cri_id][6] += 1;
                                        } else {
                                            $data[$cri_id][7] += 1;
                                        }
                                    } else {
                                        $data[$cri_id][8] += 1;
                                    }
                                } else {
                                    $data[$cri_id][8] += 1;
                                }
                                $data[$cri_id][9] += 1;
                                
                                $profile = callCurl("https://rmit.instructure.com/api/v1/users/" . $submission_list[$m]["user_id"] . "/profile?access_token=" . $token);
                                if(isset($profile["login_id"])){
                                    $student_data[$cri_id][] = array( ltrim($profile["login_id"],'S'),$profile["login_id"], $pt);
                                }
                                
                            }
                        }              
                    }
                    $page_number += 1;
                } else {
                    break;
                }
            }
            $total_data = array();
            foreach($data as $key=>$item){
                $total_data[] = $item;
            }
            $total_data[] = array("");
            $total_data[] = array("");
            $total_data[] = array("");
            foreach($student_data as $key=>$item){
            
                usort($item, "cmp");
                $total_data[] = array("SIS ID", "LOGIN ID","Marks");
                foreach($item as $val){
                    $total_data[] = $val;
                }
                $total_data[] = array("");
                $total_data[] = array("");
            }
            array_to_csv_download($total_data);
            //print_r($data);
        } 
    }
}

?>




<!DOCTYPE html>


<!--
		(©) 2022 Ishpal Sandhu <ishpal.sandhu@rmit.edu.au> & Gillian Vesty <gillian.vesty@rmit.edu.au - All Rights Reserved 
		The use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the code requires acknowledgement must be givem to the authors mentioned above.  
		THE CODE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES	OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND 
		NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, 
		OUT OF OR IN CONNECTION WITH THE CODE OR THE USE OR OTHER DEALINGS OF THE CODE.

-->
<html>
<head>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
</head>
<body>

<div class="container">


<nav class="navbar navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand">SANDHU-VESTY Rubric Criteria Extraction Model </a>
  </div>
</nav>



<br />
<div class="search-info text-center">
<form class="subscribe_form" action="?" method="get">
  <input value="<?=$course_id?>" placeholder="Course Id"  name="course_id" type="text">
  <input value="<?=$token?>" placeholder="Access token"  name="access_token" type="text">
  <input class="btn btn-primary btn-sm"  role="button"" value="submit" type="submit">
  
    
</form>
</div>
<!-- <div></div> -->
<div class="row">
    <div class="col-sm-12" id="chart_div"></div>
</div>
<div class="table-card">
<?php if($flag){ ?>
    <table class="table table-hover">
        <thead>
        <tr>
            <th>Assignment Name</th>
            <th>Assignment Due Date</th>
            <th>Assignment Link</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php for($i=0; $i < count($assignment_list); $i++) { ?>
        <tr style="background:<?= ($assignment_list[$i]['id'] == $assignment_id)?"#337ab7":"#58b6ed" ?>">
            <td><?=$assignment_list[$i]['name'] ?></td>
            <td><?=$assignment_list[$i]['due_at'] ?></td>
            <td><?=$assignment_list[$i]['html_url'] ?></td>
            <td>
                <?php 
                    if($assignment_list[$i]['id'] != $assignment_id) {
                ?>
                <button class="btn btn-secondary btn-sm" onClick="setGetParameter('assignment_id','<?=$assignment_list[$i]['id'] ?>')" > Select </button>
                <?php    
                    } 
                ?>
            </td>       
        </tr>
        <?php } ?>
        </tbody>
    </table>

    <hr />
    <?php 
    if(count($criteria_list)>0){
    ?>
        <table class="table table-hover">
            <thead>
            <tr>
                <th></th>
                <th>#</th>
                <th>Criteria Title</th>
                <th>Long Description</th>
                <th>Points</th>
            </tr>
            </thead>
            <tbody>
            <?php for($i=0; $i < count($criteria_list); $i++) { ?>
            <tr>
                <td>
                    <input type="checkbox" value="<?= $criteria_list[$i]["id"]?>" name='criteria_list[]' class="criteria_check" />
                </td>
                <td><?=$criteria_list[$i]['id'] ?></td>
                <td><?=$criteria_list[$i]['description'] ?></td>
                <td><?=$criteria_list[$i]['long_description'] ?></td>
                <td><?=$criteria_list[$i]['points'] ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
		<br>
        <button class="btn btn-primary btn-lg btn-block"onClick="selectCriterias()" > Print to Excel </button>
    <?php
    }
    ?>

<?php } else { ?>
<div>Error while parsing data!</div>
<?php } ?>
</div>

</div>
<script>

    function setGetParameter(paramName, paramValue)
    {
        var url = window.location.href;
        var hash = location.hash;
        url = url.replace(hash, '');
        if (url.indexOf(paramName + "=") >= 0)
        {
            var prefix = url.substring(0, url.indexOf(paramName + "=")); 
            var suffix = url.substring(url.indexOf(paramName + "="));
            suffix = suffix.substring(suffix.indexOf("=") + 1);
            suffix = (suffix.indexOf("&") >= 0) ? suffix.substring(suffix.indexOf("&")) : "";
            url = prefix + paramName + "=" + paramValue + suffix;
        }
        else
        {
        if (url.indexOf("?") < 0)
            url += "?" + paramName + "=" + paramValue;
        else
            url += "&" + paramName + "=" + paramValue;
        }
        window.location.href = url + hash;
    }

    function selectCriterias() {
        var criteria_ids = new Array();
        //xzyId is table id.
        $('input.criteria_check').each(function() {
          if ($(this).is(':checked')) {
            criteria_ids.push(this.value);
          }
        }).promise().done( function(){ 
            setGetParameter("criteria_ids", JSON.stringify(criteria_ids)); 
        } );
        
    }
</script>
</body>
</html>
