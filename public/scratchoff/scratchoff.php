

<?php


/**
 * user key lists:
 * (string with TTL:3600) user:{$this->openId}:score:cache => (json) the list of all scores
 * (list) user:{$this->openId}:score:checked => names of courses, which were checked by the user
 * public key:
 * (hash) user:public:score:flag_likes => (openid => flag)
 * (hash) user:public:score:score_amount => (openid => count)
 */

class ScratchOff
{
    /**
     * store the openid
     */
    public $openId;
    private $newScoreCount;

    /**
     * constants for setFlag() and getFlag()
     */
    const FLAG_CHECK = 10001;
    const FLAG_LIKE = 10002;
    const SET_FLAG_ONLY = true;

    const HIDDEN_TIMEOUT = 3600*24*30; // save for one month

    public function __construct($openId, $setFlagOnly = false)
    {
        /* openid string filter */
        $resultCount = preg_match("/([\w_=-]+)/", $openId, $match);
        $this->newScoreCount = 0;
        $this->openId = $resultCount ? $match[0] : "";
    }

    /**
     * get formated score result containing is_checked flag
     * @return array
     */
    public function getResult()
    {
        $account_info_detail = new AccountInfoController();
        $arrScore = $account_info_detail->getScoreMessage();
        return $arrScore;
    }

    /**
     * set the
     *
     * @param string $className
     * @param string $score
     * @return array
     */
    public function hideClass(string $className, string $score)
    {
        return array();
    }

    /*
     * set flags in the database
     * @param const flag type
     * @param string data to be set
     * implicit param $this->openId
     * @return mixed
     */
    public function setFlag($flagType, $data = null)
    {
        switch ($flagType) {
            case self::FLAG_CHECK:
                $status = $this->redis->rPush("user:{$this->openId}:score:checked", $data);
                //TODO: NEED TO IMPROVE
                $this->redis->lTrim("user:{$this->openId}:score:checked", 0, 30);
                return $status;
            case self::FLAG_LIKE:
                $data = (!$data) ? 0 : 1;	//avoid invalid input & set 1 as default
                return $this->redis->hSet("user:public:score:flag_likes", "{$this->openId}", $data);
            default:
                return false;
        }
    }

    /**
     * get flags in the database
     * @param const flag type
     * implicit param $this->openId
     * @return mixed
     */
    public function getFlag($flagType)
    {
        switch ($flagType) {
            case self::FLAG_CHECK:
                return $this->redis->lRange("user:{$this->openId}:score:checked", 0, -1);
                break;
            case self::FLAG_LIKE:
                return $this->redis->hGet("user:public:score:flag_likes", "{$this->openId}");
                break;
            default:
                return false;
        }
    }

    /**
     * clear all flags. often used when user unsubscribed
     */
    public function clearFlags()
    {
        $this->redis->del("user:{$this->openId}:score:checked");
        $this->redis->del("user:{$this->openId}:score:cache");
        $this->redis->hDel("user:public:score:flag_likes", $this->openId);
        $this->redis->hDel("user:public:score:score_amount", $this->openId);
    }

    /**
     * judge if the user with this openid is registered(the LIKE flag is set)
     * @return boolean
     */
    private function isRegistered()
    {
        return $this->redis->hExists("user:public:score:flag_likes", "{$this->openId}");
    }

    /**
     * pick the data we need from the array of score results
     * and append the is_checked flag into the array.
     *
     * itsl 5-30-2018 update:
     *  append the 'hidden' flag into the array.
     *
     * @param array the array containing JUST score information (from eduAdmin->getScore())
     * @param boolean specify the format of the input array, true for old format
     * @return array formatted score array
     */
    private function procScoreData($arrScore, $isOldForm = false)
    {
        $output = array();
        $checkedList = $this->getFlag(self::FLAG_CHECK);
        foreach ($arrScore as $key => $eachClass) {
            if ($isOldForm) {
                $output[$key]['class_name'] = $eachClass['name'];
                $output[$key]['class_type'] = $eachClass['type'];
                $output[$key]['score'] = $eachClass['score'];
            } else {
                $output[$key]['class_name'] = $eachClass[3];
                $output[$key]['class_type'] = $eachClass[4];
                $output[$key]['score'] = $eachClass[7];
            }

            // check if the score has been hidden.
            $output[$key]['hidden'] = false;
            $hidden = $this->redis->hGet(
                "user:{$this->openId}:score:hidden",
                $output[$key]['class_name'].$output[$key]['score']
            );
            if ($hidden === '1') {
                $output[$key]['hidden'] = true;
            }

            if (is_array($checkedList) && count($checkedList) > 0) {
                #DEBUG
                //echo "[debug] is_checked flag found.\n";
                foreach ($checkedList as $checkedName) {
                    if ($output[$key]['class_name'] == $checkedName) { 	//string comparison
                        $output[$key]['is_checked'] = true;
                        break;
                    } else {
                        $output[$key]['is_checked'] = false;
                    }
                }
            } else {
                #DEBUG
                //echo "[debug] is_checked flag doesn't exist.\n";
                $output[$key]['is_checked'] = false;
            }
        }
        /* sort the array */
        usort($output, 'isCheckedCmp');
        // uasort($output, 'isCheckedCmp');	//this will maintain the key association so it should not be used here
        return $output;
    }

    /**
     * link to the databases (MySQL)
     * PASSWORD INCLUDED
     * @return array username and password
     * @throws Exception
     */
    private function linkMysqlDb()
    {
        /* connect to the MySQL database and get username and password according to the openId */
        $database=new UserInfo();
        $userInfoArray = $database->getxuehao($this->openId);
        $account =$userInfoArray["account"];
        $password =$userInfoArray["password"];

        if (!isset($account)) {
            throw new Exception("Cannot find the account from db", 407);
        }

        $password = urlencode($password);	// url编码，用于处理包含特殊字符的密码
        return array(
            'username' => $account,
            'password' => $password
        );
    }

    /**
     * convert new form into old form for compatibility
     * @param array output from getScore()
     * @return array
     */
    private function convertToOldForm($arrScore)
    {
        $converted = array();
        $converted['status'] = $arrScore['status'];
        if ($arrScore['status'] == 200) {
            $scoreArr = array();
            foreach ($arrScore['data'] as $value) {
                $eachClass = array();
                $eachClass['name'] = $value[3];
                $eachClass['type'] = $value[4];
                $eachClass['score'] = $value[7];
                $scoreArr[] = $eachClass;
            }
            $converted['info'] = $scoreArr;
        } else {
            $converted['info'] = "";
        }
        return $converted;
    }
}

function isCheckedCmp($arrA, $arrB)
{
    return $arrA['is_checked'] - $arrB['is_checked'];
}
