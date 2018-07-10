<?php
#DEBUG

header("Content-Type: application/json; charset=utf-8");
if (isset($_GET['openid']) && isset($_POST['openid']) === false) {
    /*
     * GET_FORMAT:
     * openid=OPENID
     */
    /* get scores */
    try {
        $matchCount = preg_match("/^oULq3u[a-zA-Z0-9_-]{22}/", $_GET['openid'], $matches);
        if ($matchCount > 0) {
            $openId = $matches[0];
            unset($matches);
            $handle = new scratchOff($openId);
            $isLike = $handle->getFlag(scratchOff::FLAG_LIKE);
            if ($isLike === false) {	//if LIKE flag is not set
                $handle->setFlag(scratchOff::FLAG_LIKE, 1);
                $isLike = 1;
            }
            #DEBUG
            #manually set flags
            //echo "[debug] isLikeFlag = {$isLike}\n";
            // $handle->setFlag(scratchOff::FLAG_CHECK, "高等数学A(2)");
            #END OF DEBUG
            $result = $handle->getResult();
            $result['openid'] = $openId;
            if ($result['status'] == 200) {
                $log->debug($result['message'], $result);
                $returnArr = array(
                    'status' => 200,
                    'is_like' => $isLike,
                    'data' => $result['data']
                );
            } else {
                if (array_key_exists('debug', $result)) {
                    $log->error($result['message'], $result);
                } else {
                    $log->notice($result['message'], $result);
                }
                $returnArr = array(
                    'status' => $result['status'],
                    'is_like' => $isLike,
                    'data' => false
                );
            }
        } else {
            $log->info("invalid openid found while querying for scores.", array('openid' => base64_encode($_GET['openid'])));
            $returnArr = array(
                'status' => 405,
                'is_like' => 0,
                'data' => false
            );
        }
    } catch (Exception $e) {
        /* errcode:407 cannot find the openid in the database */
        if ($e->getCode() == 407) {
            $log->warning("a request with invalid openid received (maybe no binding)", array(
                'client IP' => $_SERVER['REMOTE_ADDR'],
                'UA' => $_SERVER['HTTP_USER_AGENT']
            ));
            $returnArr = array(
                'status' => 407,
                'is_like' => 0,
                'data' => false
            );
        }
        /* others will be treated as internal server error */
        else {
            $log->error($e->getMessage());
            $returnArr = array(
                'status' => 500,
                'is_like' => 0,
                'data' => false
            );
        }
    }
} elseif (isset($_POST['openid']) && isset($_POST['act']) && isset($_POST['data'])) {
    /*
     * POST_FORMAT:
     * IS_LIKE: openid=OPENID&act=like&data=(true|false)
     * CHECKED: openid=OPENID&act=checked&data=CLASS_NAME
     */
    $act = $_POST['act'];
    $actData = $_POST['data'];
    $matchCount = preg_match("/^oULq3u[a-zA-Z0-9_-]{22}/", $_POST['openid'], $matches);
    if ($matchCount > 0) {
        $openid = $matches[0];
        unset($matches);
        try {
            $handle = new scratchOff($openid, scratchOff::SET_FLAG_ONLY);
            switch ($act) {
                case 'like':
                    if ($actData == 'false') {
                        $handle->setFlag(scratchOff::FLAG_LIKE, 0);
                        $log->debug("set like_flag to false successfully", array("openid" => $openid));
                    } elseif ($actData == 'true') {
                        $handle->setFlag(scratchOff::FLAG_LIKE, 1);
                        $log->debug("set like_flag to true successfully", array("openid" => $openid));
                    } else {
                        $postData = file_get_contents("php://input");
                        if (strlen($postData) >= 100) {
                            $postData = substr($postData, 0, 100).'(more than 200bytes)';
                        }
                        $log->warning("a post request with invalid parameter (like != (true|false)) received", array(
                            'client IP' => $_SERVER['REMOTE_ADDR'],
                            'UA' => $_SERVER['HTTP_USER_AGENT'],
                            'postdata(base64)' => base64_encode($postData)
                        ));
                    }
                    break;
                case 'checked':
                    if (strlen($actData) <= 100) {
                        $handle->setFlag(scratchOff::FLAG_CHECK, $actData);
                        $log->debug("append content to check_flag successfully", array("openid" => $openid));
                    } else {
                        $postData = file_get_contents("php://input");
                        if (strlen($postData) >= 100) {
                            $postData = substr($postData, 0, 100).'(more than 200bytes)';
                        }
                        $log->warning("a post request with too long data received", array(
                            'client IP' => $_SERVER['REMOTE_ADDR'],
                            'UA' => $_SERVER['HTTP_USER_AGENT'],
                            'postdata(base64)' => base64_encode($postData)
                        ));
                    }
                    break;
                default:
                    break;
            }
            $returnArr = array(
                'status' => 200,
            );
        } catch (Exception $e) {
            if ($e->getCode() == 407) {
                $log->info("a request with invalid openid received", array(
                    'client IP' => $_SERVER['REMOTE_ADDR'],
                    'UA' => $_SERVER['HTTP_USER_AGENT']
                ));
                $returnArr = array(
                    'status' => 403
                );
            } else {
                $log->error($e->getMessage());
                $returnArr = array(
                    'status' => 500
                );
            }
        }
    } else {
        $log->info("invalid openid found while handling post requests.", array('openid' => base64_encode($_POST['openid'])));
        $returnArr = array(
            'status' => 403
        );
    }
} else {
    /* invalid parameter */
    $log->info("a request with invalid parameter received", array(
        'client IP' => $_SERVER['REMOTE_ADDR'],
        'UA' => $_SERVER['HTTP_USER_AGENT']
    ));
    $returnArr = array(
        'status' => 405,
        'is_like' => 0,
        'data' => false
    );
}

echo json_encode($returnArr);
