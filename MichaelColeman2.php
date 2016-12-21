<?php  //even faster! 1.1s...
/* Cloud Team Programming Challenge by
    Michael Coleman
    mike007coleman@gmail.com
 
    runs on my box
    commandline 
        Script Executed in 1.7721011638641 seconds
        Max memory usage:39210824
    browser
        Script Executed in 1.7406542301178 seconds
        Max memory usage:39265632
		
		
 */
if (php_sapi_name() == "cli") {
    // In cli-mode
    if(!isset($argv[1])){
        die("\nusage: ".$argv[0] . ' path_to_file.txt');
    }
    $path_to_file = $argv[1];
} else {
    // Not in cli-mode
    if(!isset($_REQUEST['file'])){
        die("\nusage: ". $_SERVER['HTTP_HOST']. $_SERVER['SCRIPT_NAME']. "?file=path_to_file.txt");
    }
    $path_to_file = $_REQUEST['file'];
}

//$path_to_file = 'wordsEn.txt';

        

$pattern = array('a'=>1,'b'=>2,'c'=>4,'d'=>8,'e'=>16,'f'=>32,'g'=>64,'h'=>128,'i'=>256,'j'=>512,'k'=>1024,'l'=>2048,'m'=>4096,
'n'=>8192,'o'=>16384,'p'=>32768,'q'=>65536,'r'=>131072,'s'=>262144,'t'=>524288,'u'=>1048576,'v'=>2097152,'w'=>4194304,'x'=>8388608,'y'=>16777216,'z'=>33554432,
"'"=>67108864,'.'=>134217728,'#'=>268435456);

/*
,'zy xwvu tsrq ponm lkji hgfe dcba
0000 0000 0000 0000 0000 0000 0000

compare abc & dabc
      cba 
0000 0111  =>  7
     dcba 
0000 1111  => 15
  
 15-7 = 8 
0000 1000  CHAIN! only one bit is on (power of 2)

   cba 0000 0111  =>  7
  feba 0011 0011  => 51
51-7 = 44
0010 1100  No CHAIN 3 bits on
  

PHP_INT_MAX = 2147483647 => 32 bit -1 bit for the sign. 31 chars to play with
   if multibyte characters we'd need to use special math functions (gmp or BC) 
    to handle BIG numbers or we lose precision.
   these math functions are slow so a different approach other than a bitmask
   maybe levenshtein or calculate the edit distance to find words of len+1?
   prime product approach was too slow and used too much memory because of the HUGE numbers
  

Knowns:
 *  anagram++ is always has a greater mask value
 * some words will not show up in the chain if they have the same mask ie. ac = 5 && ca = 5
 *   the way I'm doing it, words with duplicate masks will be elimininated 
 *   (: shorter list :) eliminates 11960 duplicates in original file
 * 
 * I can terminate once I find a chain of length N within a range. ie
 * ranges [1-6], [8-10] .. if I find a chain in the range[1,6] = 5 at any point there
 * is no need to continue with that range or even the next since N for the 2nd range is 2 max
 * 
 * optimization start with the largest range first? because if I find a chain longer than the other ranges
 * then I don't have to look at them.
 * 
 * I bet it'd run faster if I used datastructures
 *      Hash table? Trie? Wish I had more time to check em out
 * is PHP really slower than say C# ?
 * 
 * 
 * 
questions: 
 * how are apostrophes handled?  are they ignored or part of the chain?
 * multibyte chars ? 
 * only english alphabet ? chars with accents?  
 * always lower case?
 * always presorted?

 */
$mask = 0; 
function getmask(&$word){
    //globals are bad m'kay?  but they sure are fast
    //this shaves half a second off the execution time
    global $pattern, $mask; 
    $mask = 0;
    foreach(str_split($word) as $letter){
        $mask += $pattern[$letter];
    }
    return $mask;
}
//Compliment and compare
function powtwo_cc ($x){
  return (($x != 0) && (($x & (~$x + 1)) == $x));
}

//(Decrement and Compare) //faster powof2 function 
function powtwo_dc($x){
    return $x && !($x & ($x - 1));
}

/*
 * get all the ranges (chain length) based on the buckets
 * sorted descending on the idea that if we find a chain longer than the other
 * ranges we can disregard them
 */
function get_ranges($array_in){    
    $range_array = array();
    $start = $end = current($array_in);
    
    foreach($array_in as $range){
        if($range - $end > 1){  
            $idx = $end-$start+1;
            $range_array[$idx][] = array('s'=>$start, 'e'=>$end, 'l'=>$idx);
            $start = $range;
        }
        $end = $range;
    }    
    $idx = $end-$start+1;
    $range_array[$idx][] = array('s'=>$start, 'e'=>$end, 'l'=>$idx);    
    krsort($range_array);
    
    return $range_array;
}

$chains = array();
$num_chains = 0;
$finished = false;
$max_chain = 0;

function find_next_link($mask1, $start, $end){
    global $chains, $num_chains, $max_chain, $finished, $buckets;  //globals are bad m'kay?  but they sure are faster
    $found = false;
    if($finished) return;
    if(!isset($mask1)){ //start of a new range
        
        foreach($buckets[$end] as $mask2 => $word2){            
            $chains[$num_chains] = array(
                    'w'=>array($word2), 
                    'l'=>1);            
            if( find_next_link($mask2, $start, $end-1)){
                break;
            }
            else{            
                ++$num_chains;
            }
        }       
        
        if($start < ($end-1)){            
            if(find_next_link(NULL, $start, $end-1)){
                return true;
            }
        }
        return;
    }

    foreach($buckets[$end] as $mask2 => $word2){
        
        if(powtwo_dc($mask1-$mask2)){
            $chains[$num_chains]['w'][] = $word2;            
            ++$chains[$num_chains]['l'];
            if($chains[$num_chains]['l'] > $max_chain){
                $max_chain = $chains[$num_chains]['l'];
            }
            if($start == $end){ //last link in range                
                $finished = true;
                return true;
            }
            if( find_next_link($mask2, $start, $end-1) ){                
                return true;                
            }
            else{                
                return;
            }
        }        
    }
        
    return $found;    
}


$dictionary = file ( $path_to_file, FILE_IGNORE_NEW_LINES );
if(!$dictionary){
    die ("Failed to open file $path_to_file");
}
$buckets = array();
foreach($dictionary as $tword){
    $word = strtolower(trim($tword)); //string lower adds ~.1 sec
    /* readability vs speed - inline getmask to avoid function call overhead? overhead*O(n) */    
    $buckets[ strlen($word) ][ getmask($word) ] = $word;//array('w'=>$word);
}

$bucket_keys = array_keys($buckets);
asort($bucket_keys);
//print_r($bucket_keys);
$ranges = get_ranges($bucket_keys);



echo '<pre>', PHP_EOL;
//print_r($ranges); //15
//$j = 17;
//$ranges_test[ $j ] = array(array('s'=>1,'e'=> $j, 'l'=>$j));
//print_r($ranges_test);



foreach($ranges as $range_len){
    foreach($range_len as $range){
        //$rangeSize = $range['l'];//$range['e'] - $range['s'];
        if($max_chain > $range['l']){
            echo 'Skipping range [', $range['s'], ',', $range['e'], ']  we already have a larger chain than this range', PHP_EOL;
        }
        else{
            echo 'Searching range [', $range['s'], ',', $range['e'], ']' , PHP_EOL;
            find_next_link(NULL, $range['s'], $range['e']);
        }
    }
    
}
echo "Dictionary Length:".count($dictionary), PHP_EOL;
echo 'Maximum chain found:', $max_chain, PHP_EOL;


$longest_chain = array_pop($chains);
print_r($longest_chain);



       
$time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
echo 'Script Executed in ', $time, ' seconds', PHP_EOL; //0.062957048416138 seconds
echo 'Max memory usage:' , memory_get_peak_usage();
echo '</pre>', PHP_EOL;
       