<?php
namespace socket;
/**
 * Class PHPStream
 * @package socket
 */
class PHPStream {
    /**
     * @var int
     */
    var $len;
    /**
     * @var string
     */
    var $data;

    /**
     * 构造函数
     */
    function __construct() {
		$this->len = 0;
		$this->data = '';
	}

    /**
     * 析构函数 生命周期结束时候 重置data len
     */
    function  __destruct(){
        $this->len = 0;
        $this->data = '';
    }


    /**
     * 无符号 char 1 bit  0 ~ 2^8-1
     * @param $num
     */
    function WriteUChar($num) {
		//echo "UChar " . $num . "<br />";
		$this->len += 1;
		$this->data .= pack('C', $num);
	}


    /**
     * 有符号 char 1 bit  -2^7 ~ 2^7-1
     * @param $num
     */
    function WriteChar($num) {
		//echo "Char " . $num . "<br />";
		$this->len += 1;
		$this->data .= pack('c', $num);
	}



    /**
     * 无符号 short 2 bit  0~65535
     * @param $num
     */
    function WriteUShort($num) {
		//echo "UShort " . $num . "<br />";
		$this->len += 2;
		$this->data .= pack('S', $num);
	}

    /**
     * 有符号 short 2 bit  -32768~+32767
     * @param $num
     */
    function WriteShort($num) {
		//echo "Short " . $num . "<br />";
		$this->len += 2;
		$this->data .= pack('s', $num);
	}



    /**
     * unsigned long 4 bit 0~4294967295（0~(2^32-1)
     * @param $num
     */
    function WriteULong($num) {
		//echo "ULong " . $num . "<br />";
		$this->len += 4;
		$this->data .= pack('L', $num);
	}



    /**
     * signed long -2147483648~2147483647（-2^31~(2^31-1)）
     * @param $num
     */
    function WriteLong($num) {
		//echo "Long " . $num . "<br />";
		$this->len += 4;
		$this->data .= pack('l', $num);
	}



    /**	
	 * 以NULL 字节填充字符串
     * @param $str
     * @param $len
     */
    function WriteString($str, $len) {
		//echo "String " . $str . "<br />";
		$this->len += $len;
		$this->data .= pack('a' . $len, $str);
	}
	//8个字节长度数字

    /**
     * signed long long
     * @param $num
     */
    function WriteINT64($num) {
		$num = floatval($num);
		$numL32 = $num & 0xFFFFFFFF;
		$numH32 = floor($num / pow(2, 32));

		$this->len += 4;
		$this->data .= pack('L', $numL32);
		$this->len += 4;
		$this->data .= pack('L', $numH32);
	}

    //

    /**
	 *8个字节长度数字 无符号
     * unsigned long long
     * @param $num
     */
    function WriteUINT64($num) {
        //echo "INT64 " . $num . "<br />";
        $num = floatval($num);
        $numL32 = $num & 0xFFFFFFFF;
        $numH32 = floor($num / pow(2, 32));

        $this->len += 4;
        $this->data .= pack('l', $numL32);
        $this->len += 4;
        $this->data .= pack('l', $numH32);
    }
}
