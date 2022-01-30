<?php

namespace app\controllers;

use common\models\SystemLog;
use Yii;
use common\models\UserLogin;
use common\models\UserLoginSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\ServerErrorHttpException;

/**
 * UserLoginController implements the CRUD actions for UserLogin model.
 */
class UserLoginController extends Controller
{
    public const LOGIN_SESSION_STATUS_LOGGED = 'logged';
    public const LOGIN_SESSION_STATUS_LOGOUT = 'logout';
    public const LOGIN_SESSION_RENEW = 'renewSession';

    /**
     * {@inheritdoc}
     */
    // TODO: add control access.
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id): ?UserLogin
    {
        if (($model = UserLogin::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * @throws \JsonException
     * @throws ServerErrorHttpException
     * @throws NotFoundHttpException
     * @throws \yii\db\StaleObjectException
     */
    public function actionCheckLogoutStatus()
    {
        if (Yii::$app->request->isPost && isset($_POST['logoutTimer'])) {

            if (!Yii::$app->user->isGuest) {
                $userLoginSession = UserLogin::find()
                    ->where(['user_id' => Yii::$app->user->identity->id])
                    ->andWhere(['session_id' => Yii::$app->session->id])
                    ->orderBy(['logged' => SORT_DESC])
                    ->one();
                if (!$userLoginSession) {
                    throw new NotFoundHttpException(\sprintf('User with id: %s nor found', Yii::$app->user->identity->id));
                }

                $sessionLogged = $userLoginSession->session_logged;
                $timeNow = new \DateTime('now', new \DateTimeZone(Yii::$app->params['defaults']['systemTimeZone']));
                $timeNowUTC = $timeNow->getTimestamp();

                if (($timeNowUTC - $sessionLogged) > (Yii::$app->params['systemTimeout']['authTimeout'])) {

                    $systemLog = new SystemLog();
                    $systemLog->user_id = Yii::$app->user->identity->id;
                    $systemLog->instance = Yii::$app->user->identity->instance;
                    $systemLog->message_short = (Yii::$app->user->identity->first_name ?? '') . ' ' . (Yii::$app->user->identity->last_name ?? '') . ' logged out';
                    $systemLog->message = (Yii::$app->user->identity->first_name ?? '') . ' ' . (Yii::$app->user->identity->last_name ?? '') . ' logged out for this instance ' . Yii::$app->user->identity->instance . ' from ip: ' . Yii::$app->request->getUserIP();
                    $dataFormat = [
                        'event' => 'logout',
                        'user' => Yii::$app->user->identity->id,
                        'ip' => Yii::$app->request->getUserIP(),
                    ];
                    $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
                    $systemLog->save();

                    // change value for cookies
                    $cookies = Yii::$app->response->cookies;
                    $cookies->remove('userSession');
                    Yii::$app->cache->flush();

                    $userLoginCookie = new Cookie([
                        'name' => 'userSession',
                        'value' => '0',
                        'httpOnly' => false,
                    ]);
                    Yii::$app->response->cookies->add($userLoginCookie);

                    Yii::$app->user->logout();
                    echo (string)'logout';
                }
            } else {
                // user not logged loggout
                $cookies = Yii::$app->response->cookies;
                $cookies->remove('userSession');
                Yii::$app->cache->flush();

                $userLoginCookie = new Cookie([
                    'name' => 'userSession',
                    'value' => '0',
                    'httpOnly' => false,
                ]);

                Yii::$app->response->cookies->add($userLoginCookie);
                Yii::$app->user->logout();
                echo (string)'logout';
            }
        }
        else {
            $cookies = Yii::$app->response->cookies;
            $cookies->remove('userSession');
            Yii::$app->cache->flush();

            $userLoginCookie = new Cookie([
                'name' => 'userSession',
                'value' => '0',
                'httpOnly' => false,
            ]);
            Yii::$app->response->cookies->add($userLoginCookie);
            throw new ServerErrorHttpException('Internal server error', 500);
        }
    }


    /**
     * @throws \JsonException
     * @throws ServerErrorHttpException
     * @throws NotFoundHttpException
     * @throws \yii\db\StaleObjectException
     */
    public function actionNewChechLogoutStatus()
    {
        // user is not logged or not exists cookie userSession (delete manually)
        if (Yii::$app->user->isGuest || (isset($_POST['directLogout']) && $_POST['directLogout'] === 'directLogout')) {
            $cookies = Yii::$app->response->cookies;
            //$cookies->remove('userSession');
            Yii::$app->cache->flush();

            $userLoginCookie = new Cookie([
                'name' => 'userSession',
                'value' => '0',
                'httpOnly' => false,
            ]);
            $cookies->add($userLoginCookie);
            Yii::$app->user->logout();
            echo (string)'logout';
            //throw new ServerErrorHttpException('Internal server error', 500);
        }

        // user is logged and exists cookie userSession
        if (Yii::$app->request->isPost && (isset($_POST['logoutTimer']) && $_POST['logoutTimer'] === 'logoutTimer')) {

            $userLoginSession = UserLogin::find()
                ->where(['user_id' => Yii::$app->user->identity->id])
                ->andWhere(['session_id' => Yii::$app->session->id])
                ->orderBy(['logged' => SORT_DESC])
                ->one();

            if (!$userLoginSession) {
                throw new NotFoundHttpException(\sprintf('User with id: %s nor found', Yii::$app->user->identity->id));
            }

            $sessionLogged = $userLoginSession->session_logged;
            $timeNow = new \DateTime('now', new \DateTimeZone(Yii::$app->params['defaults']['systemTimeZone']));
            $timeNowUTC = $timeNow->getTimestamp();

            // check session is expired
            if ($timeNowUTC - $sessionLogged > Yii::$app->params['defaults']['sessionExpired']) {
                // save in system log
                $systemLog = new SystemLog();
                $systemLog->user_id = Yii::$app->user->identity->id;
                $systemLog->instance = Yii::$app->user->identity->instance;
                $systemLog->message_short = (Yii::$app->user->identity->first_name ?? '') . ' ' . (Yii::$app->user->identity->last_name ?? '') . ' logged out';
                $systemLog->message = (Yii::$app->user->identity->first_name ?? '') . ' ' . (Yii::$app->user->identity->last_name ?? '') . ' logged out for this instance ' . Yii::$app->user->identity->instance . ' from ip: ' . Yii::$app->request->getUserIP();
                $dataFormat = [
                    'event' => 'logout',
                    'user' => Yii::$app->user->identity->id,
                    'ip' => Yii::$app->request->getUserIP(),
                ];
                $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
                $systemLog->save();

                // change cookie userSession
                $cookies = Yii::$app->response->cookies;
                $cookies->remove('userSession');
                Yii::$app->cache->flush();
                $userLoginCookie = new Cookie([
                    'name' => 'userSession',
                    'value' => '0',
                    'httpOnly' => false,
                ]);
                $cookies->add($userLoginCookie);

                Yii::$app->user->logout();
                echo (string)'logout';
            }

        } else {
            throw new ServerErrorHttpException('Internal server error', 500);
        }


    }

    /**
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     * @throws \JsonException
     */
    public function actionRenewUserSession()
    {
        if ($_POST['RenewlogoutTimer'] && Yii::$app->session->isActive) {

            $userLoginSession = UserLogin::find()
                ->where(['user_id' => Yii::$app->user->identity->id])
                ->andWhere(['session_id' => Yii::$app->session->id])
                ->orderBy(['logged' => SORT_DESC])
                ->one();

            if (!$userLoginSession) {
                throw new NotFoundHttpException(\sprintf('User with id %s not found', Yii::$app->user->identity->id));
            }

            $timeNow = new \DateTime('now', new \DateTimeZone(Yii::$app->params['defaults']['systemTimeZone']));
            $timeNowUTC = $timeNow->getTimestamp();
            $sessionTimeout = Yii::$app->params['systemTimeout']['authTimeout'];

            if (($timeNowUTC - $userLoginSession->session_logged) > $sessionTimeout) {
                $userLoginSession->expire = $timeNowUTC + $sessionTimeout;
                $userLoginSession->save();
            }
            try {
                echo json_encode([
                    'renewSession' => self::LOGIN_SESSION_RENEW, // 'renewSession'
                    'renewCounterValue' => Yii::$app->params['systemTimeout']['authTimeout'],
                ], JSON_THROW_ON_ERROR);
            } catch (ServerErrorHttpException $exception){
                throw new ServerErrorHttpException('Internal server error', 500);
            }
        } else {
            try{
                echo json_encode([
                    'renewSession' => self::LOGIN_SESSION_RENEW, // 'renewSession'
                    'renewCounterValue' => 0,
                ], JSON_THROW_ON_ERROR);
            } catch (ServerErrorHttpException $exception){
                throw new ServerErrorHttpException('Internal server error', 500);
            }
        }
    }
}

