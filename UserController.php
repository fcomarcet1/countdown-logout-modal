<?php

namespace backend\controllers;

use backend\components\BaseController;
use common\models\LoginForm;
use common\models\SystemLog;
use common\models\User;
use common\models\UserLogin;
use common\models\UserLoginSession;
use common\models\UserSearch;
use common\models\UserSettings;
use Imagine\Image\ManipulatorInterface;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Html;
use yii\web\Cookie;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;
use yii\widgets\ActiveForm;

class UserController extends BaseController
{
    public const LOGIN_SESSION_STATUS_LOGGED = 'logged';
    public const LOGIN_SESSION_STATUS_LOGOUT = 'logout';
    public const LOGIN_SESSION_RENEW = 'renewSession';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => [
                    'index',
                    'setTestmode',
                    'view',
                    'register',
                    'verify',
                    'emailsent',
                    'profile',
                    'profilesettings',
                    'actionForgotpw',
                    'resetpw',
                    'pwchange',
                    'addemailpw',
                    'addemail',
                    'resendVerificationEmail',
                    'emailchange',
                    'addtagid',
                    'mergeaccounts',
                    'mergeAccountsConfirm',
                    'logout',
                ],
                'denyCallback' => function ($rule, $action) {
                    throw new ForbiddenHttpException("You do not have permission to do this action");
                },
                'rules' => [
                    [
                        'actions' => ['index',],
                        'allow' => (isset(Yii::$app->user->identity)),
                        'matchCallback' => function ($rule, $action) {
                            $hasAccess = false;
                            if (Yii::$app->user->identity->hasAccess('siteAdmin', 'read')) {
                                $hasAccess = true;
                            }
                            if (Yii::$app->user->identity->hasAccess('systemAdmin', 'read')) {
                                $hasAccess = true;
                            }
                            return $hasAccess;
                        },
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => [
                            'logout',
                            'view',
                            'index',
                            'profile',
                            'profilesettings',
                            'pwchange',
                            'emailchange',
                            'addtagid',
                            'SetTestmode',
                            'mergeaccounts',
                            'mergeAccountsConfirm',
                            'resendVerificationEmail',
                        ],
                        'allow' => true,
                        'matchCallback' => function ($rule, $action) {
                            return (isset(Yii::$app->user->identity));
                        },
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['register'],
                        'allow' => true,
                        'roles' => ['?']
                    ],
                    [
                        'actions' => ['forgotpw'],
                        'allow' => true,
                        'roles' => ['?']
                    ],
                    [
                        'actions' => ['emailsent', 'verify'],
                        'allow' => true,
                        'roles' => ['?']
                    ],

                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'index' => ['GET'],
                    'view' => ['GET'],
                    'profile' => ['GET'],
                    'register' => ['GET', 'POST'],
                    'profilesettings' => ['GET'],
                    'forgotpw' => ['GET', 'POST'],
                    'pwchange' => ['GET', 'POST'],
                    'emailsent' => ['GET', 'POST'],
                    'emailchange' => ['GET', 'POST'],
                    'verify' => ['GET', 'POST'],
                    'logout' => ['POST'],
                    //'delete' => ['POST' ,'DELETE'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {

        $searchModel = new UserSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionSetTestmode()
    {
        if ($_POST['value'] === 'true') {
            $_SESSION['userSetTestMode'] = true;
            $systemLog = new SystemLog();
            $systemLog->user_id = Yii::$app->user->identity->id;
            $systemLog->instance = Yii::$app->user->identity->instance;
            $systemLog->message_short = (Yii::$app->user->identity->first_name ?? '') . ' ' . (Yii::$app->user->identity->last_name ?? '') . ' entered test mode';
            $systemLog->message = (Yii::$app->user->identity->first_name ?? '') . ' ' . (Yii::$app->user->identity->last_name ?? '') . ' entered test mode';
            $dataFormat = [
                'event' => 'testmode',
                'user' => Yii::$app->user->identity->id,
                'value' => 'entered',
            ];
            $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
            $systemLog->save();

        } else {
            if (isset($_SESSION['userSetTestMode'])) {
                unset($_SESSION['userSetTestMode']);
            }
            $systemLog = new SystemLog();
            $systemLog->user_id = Yii::$app->user->identity->id;
            $systemLog->instance = Yii::$app->user->identity->instance;
            $systemLog->message_short = (Yii::$app->user->identity->first_name ?? '') . ' ' . (Yii::$app->user->identity->last_name ?? '') . ' exited test mode';
            $systemLog->message = (Yii::$app->user->identity->first_name ?? '') . ' ' . (Yii::$app->user->identity->last_name ?? '') . ' exited test mode';
            $dataFormat = [
                'event' => 'testmode',
                'user' => Yii::$app->user->identity->id,
                'value' => 'exited',
            ];
            $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
            $systemLog->save();
        }
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionRegister()
    {
        $this->layout = Yii::$app->params['layout']['authentication'];
        $model = new User();
        $model->scenario = 'registration';
        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        if ($model->load(Yii::$app->request->post())) {
            $model->email = strtolower($model->email);
            $model->first_name = ucwords($model->first_name);
            $model->last_name = ucwords($model->last_name);
            if ($model->temp_password == $model->retype_password) {
                $model->cid = md5(($model->email . uniqid('', true)));
                $model->password = Yii::$app->getSecurity()->generatePasswordHash($model->temp_password);
            }
            $model->registered = $this->systemTime;
            $model->auth_key = \Yii::$app->security->generateRandomString();
            $model->access_token = \Yii::$app->security->generateRandomString();
            $model->instance = \Yii::$app->params['default_site_settings']['instance'];
            if ($model->save()) {
                $systemLog = new SystemLog();
                $systemLog->user_id = $model->id;
                $systemLog->instance = $model->instance;
                $systemLog->message_short = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' registered';
                $systemLog->message = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' registered for to: ' . $model->instance . ' from ip: ' . Yii::$app->request->getUserIP();
                $dataFormat = [
                    'event' => 'registered',
                    'user' => $model->id,
                    'instance' => $model->instance,
                    'ip' => Yii::$app->request->getUserIP(),
                ];
                $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
                $systemLog->save();
                $result = $this->sendVerificationLink($model);
                return $this->redirect(['emailsent']);
            }
            $message = Yii::t('core_user', 'Registration failed! Please contact <a href="mailto:{email}">{email}</a> and send the error message below.', ['email' => Yii::$app->params['default_site_settings']['support_email']]) . '<br>';
            $message .= Html::errorSummary($model);
            $_SESSION['message'] = $message;

            return $this->redirect(['/site/sysmes']);
        }
        return $this->render('register', [
            'model' => $model,
        ]);
    }

    protected function findModel($id)
    {
        if (($model = User::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException(Yii::t('core_system', 'The requested page does not exist'));
    }

    public function actionVerify($cid)
    {
        $model = User::find()->where(['cid' => $cid])->one();
        $message = '<div class="site-index text-center">
                            ' . Yii::t('core_user', '<h4>Verification failed!</h4>
                        <strong>Verification code {cid} cannot be found or is invalid.</strong><br>Please contact the support for further information!<br>E-mail: <a href="mailto:{support_email}">{support_email}</a><br><i>If you have previously verified your email, you can ignore this message and login.</i>', ['cid' => $cid, 'support_email' => Yii::$app->params['default_site_settings']['support_email']]) . '<br>' . Html::a(Yii::t('core_system', 'Login'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                        </div>';
        if ($model) {
            if ($model->email_status) {
                if ($model->email_status === 'verified') {
                    $message = '<div class="site-index text-center">
                            ' . Yii::t('core_user', '<h4>This email has already been verified!</h4>Click the button below to continue to the login page') . '<br>' . Html::a(Yii::t('core_system', 'Login'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                        </div>';
                } else {
                    $model->email_status = 'verified';
                    //$model->temp_password='123Mmmm';
                    //$model->retype_password='123Mmmm';
                    if ($model->save()) {
                        $systemLog = new SystemLog();
                        $systemLog->user_id = $model->id;
                        $systemLog->instance = $model->instance;
                        $systemLog->message_short = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' verified email';
                        $systemLog->message = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' verified email for this instance: ' . $model->instance . ' from ip: ' . Yii::$app->request->getUserIP();
                        $dataFormat = [
                            'event' => 'verified',
                            'user' => $model->id,
                            'instance' => $model->instance,
                            'ip' => Yii::$app->request->getUserIP(),
                        ];
                        $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
                        $systemLog->save();
                        if (Yii::$app->params['loginOptions']['allowEmail'] == true) {
                            $message = '<div class="site-index text-center">
                            ' . Yii::t('core_user', '<h4>Thank you for verifying your email address!</h4>
                        You may now login using your email.') . '<br>' . Html::a(Yii::t('core_system', 'Go to login'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                        </div>';
                        } else {
                            $message = '<div class="site-index text-center">
                            ' . Yii::t('core_user', '<h4>Thank you for verifying your email address!</h4>
                        <br>Your email has been added to your profile successfully.') . '<br>' . Html::a(Yii::t('core_system', 'Continue'), '/site/index', ['class' => 'btn btn-primary mt-4']) . '
                        </div>';
                        }
                        $this->sendVerificationEmail($model);
                    } else {
                        $message = '<div class="site-index text-center">
                            ' . Yii::t('core_user', '<h4>Verification failed!</h4>
                        Unfortunitely your email verification failed, we are now sending you another verification link.<br>If the error persists please contact <a href="mailto:{support_email}">{support_email}</a> and send the error message below.', ['support_email' => Yii::$app->params['default_site_settings']['support_email']]) . '<br>' . Html::a(Yii::t('core_system', 'Index'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                        </div>';
                        $message .= Html::errorSummary($model);
                        $this->sendRepeatVerificationLink($model);
                        $this->layout = Yii::$app->params['layout']['authentication'];
                        $_SESSION['message'] = $message;

                        return $this->redirect(['/site/sysmes']);
                    }
                }
            }
        }
        $this->layout = Yii::$app->params['layout']['authentication'];
        $_SESSION['message'] = $message;

        return $this->redirect(['/site/sysmes']);
    }

    public function actionEmailsent()
    {
        $this->layout = Yii::$app->params['layout']['authentication'];
        $message = '<div class="site-index text-center">' .
            Yii::t('core_user', '<h4>Thank you for registering to {site_name}!</h4>
                        <br>A verification email has been sent to the email address provided by you. <br> Please check your inbox (and the spam folder as well) and click on the verification link in the email. <br>After your email has been verified you may login!', ['site_name' => (Yii::$app->params['default_site_settings']['site_name'] ?? 'SmartAdmin')]) . '<br>' . Html::a(Yii::t('core_system', 'Continue'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                    </div>';
        $_SESSION['message'] = $message;
        return $this->redirect(['/site/sysmes']);
    }

    public function actionProfile($id = null)
    {
        if ($id === null) {
            $id = Yii::$app->user->identity->id;
        }
        $model = $this->findModel($id);
        return $this->render('profile', array(
            'model' => $model
        ));
    }

    public function actionProfileSettings($id = null)
    {
        if ($id === null) {
            $id = Yii::$app->user->identity->id;
        }
        $model = $this->findModel($id);
        return $this->render('profile-settings', array(
            'model' => $model
        ));
    }

    public function actionForgotpw()
    {
        $this->layout = Yii::$app->params['layout']['authentication'];
        if (Yii::$app->request->post()) {
            $email = strtolower(Yii::$app->request->post('email'));
            $model = User::findOne(['email' => $email, 'instance' => Yii::$app->params['default_site_settings']['instance']]);
            if (isset($model->email_status) && $model->email_status === 'verified') {
                $model->scenario = 'pwreset';
                $hash = md5($model->email . '_' . $model->cid);
                $userSettingCheck = UserSettings::findOne(['setting' => 'pwResetTime', 'user_id' => $model->id]);
                if (!isset($userSettingCheck)) {
                    $userSetting = new UserSettings();
                    $userSetting->user_id = $model->id;
                    $userSetting->setting = 'pwResetTime';
                    $userSetting->value = (string)$this->systemTime;
                    $userSetting->save();
                } else {
                    $userSettingCheck->value = (string)$this->systemTime;
                    $userSettingCheck->save();
                }
                $model->temp_password = $model->retype_password = '123Abc';
                if ($model->save()) {
                    $systemLog = new SystemLog();
                    $systemLog->user_id = $model->id;
                    $systemLog->instance = $model->instance;
                    $systemLog->message_short = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' email sent for reset password';
                    $systemLog->message = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' email sent for reset password for this instance: ' . $model->instance . 'from ip: ' . Yii::$app->request->getUserIP();
                    $dataFormat = [
                        'event' => 'emailSentResetPw',
                        'user' => $model->id,
                        'instance' => $model->instance,
                        'ip' => Yii::$app->request->getUserIP(),
                    ];
                    $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
                    $systemLog->save();
                    $this->sendPasswordReset($model, $hash);
                    $message = '<div class="site-index text-center">' .
                        Yii::t('core_user', 'An email has been sent to {email} with a link to reset your password!<br>Check your inbox (the spam folder as well) and follow the link in the email, on the page that opens, type in your new password. Once your new password has been saved you can use it to log in to your account.<br>The link will be valid for 2 hours from now.', ['email' => $email]) . '<br>' . Html::a(Yii::t('core_system', 'Continue'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                                </div>';
                    $_SESSION['message'] = $message;
                    return $this->redirect(['/site/sysmes']);
                }
            } else {
                if (isset($model->email_status) && $model->email_status === 'unverified') {
                    $message = '<div class="site-index text-center">' .
                        Yii::t('core_user', 'The email you provided: {email} is not yet verified!<br>Password reset is not possible. Find the orignal verification email you have received and click on the verification link first to activate your account.', ['email' => $email]) . '<br>' . Html::a(Yii::t('core_user', 'Back to Forgot Password'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                                </div>';
                } else {
                    $message = '<div class="site-index text-center">' .
                        Yii::t('core_user', 'The email you provided: {email} is not in our database!<br>Password reset is not possible. Check for spelling errors and re-type it.', ['email' => $email]) . '<br>' . Html::a(Yii::t('core_user', 'Back to Forgot Password'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                                </div>';
                }
                $_SESSION['message'] = $message;
                return $this->redirect(['/site/sysmes']);
            }
        }
        return $this->render('forgotpw');
    }

    public function actionResetpw($id = null, $hash = null)
    {
        $this->layout = Yii::$app->params['layout']['authentication'];
        $model = $this->findModel($id);
        if (isset($model)) {
            $model->scenario = 'pwreset';
            $userSetting = UserSettings::findOne(['user_id' => $model->id, 'setting' => 'pwResetTime']);
            if (isset($userSetting)) {
                $localHash = md5($model->email . '_' . $model->cid);
                $DBTime = strtotime($userSetting->value);
                $localTime = strtotime($this->systemTime);
                $diff = ($localTime - $DBTime) / 60;
                if ($localHash === $hash && $diff < 120) {
                    if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
                        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                        return ActiveForm::validate($model);
                    } elseif ($model->load(Yii::$app->request->post())) {
                        if ($model->temp_password) {
                            $model->password = Yii::$app->getSecurity()->generatePasswordHash($model->temp_password);
                        }
                        if ($model->save()) {
                            $systemLog = new SystemLog();
                            $systemLog->user_id = $model->id;
                            $systemLog->instance = $model->instance;
                            $systemLog->message_short = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' reset the account password';
                            $systemLog->message = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' reset the password for instance: ' . $model->instance . 'from ip: ' . Yii::$app->request->getUserIP();
                            $dataFormat = [
                                'event' => 'resetPw',
                                'user' => $model->id,
                                'instance' => $model->instance,
                                'ip' => Yii::$app->request->getUserIP(),
                            ];
                            $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
                            $systemLog->save();
                            $userSetting->delete();
                            $message = '<div class="site-index text-center">' .
                                Yii::t('core_user', 'You have successfully re-set your password. You may now login with the new password!') . '<br>' . Html::a(Yii::t('core_system', 'Continue'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                                        </div>';
                            $_SESSION['message'] = $message;
                            return $this->redirect(['/site/sysmes']);
                        }
                    } else {
                        return $this->render('resetpw', [
                            'model' => $model,
                        ]);
                    }
                } else {
                    $message = '<div class="site-index text-center">' .
                        Yii::t('core_user', 'The password reset security code is either invalid or it has timed out! <br>Please try resetting your password again.<br>If the problem persists do not hesitate to contact the support for further information! <br> E-mail: <a href="mailto:{support_email}">{support_email}</a>', ['support_email' => Yii::$app->params['default_site_settings']['support_email']]) . '<br>' . Html::a(Yii::t('core_system', 'Continue'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                                </div>';
                    $_SESSION['message'] = $message;
                    return $this->redirect(['/site/sysmes']);
                }
            } else {
                $message = '<div class="site-index text-center">' .
                    Yii::t('core_system', 'This password reset link has already been used! You cannot use it twice.<br>If you still need to reset your password you must request a new link.<br>Please contact the support for further information! <br>E-mail: <a href="mailto:{support_email}">{support_email}</a>', ['support_email' => Yii::$app->params['default_site_settings']['support_email']]) . '<br>' . Html::a(Yii::t('core_system', 'Continue'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                            </div>';
                $_SESSION['message'] = $message;
                return $this->redirect(['/site/sysmes']);
            }
        } else {
            $message = '<div class="site-index text-center">' .
                Yii::t('core_user', 'User not found or the password reset link is invalid!') . '<br>' . Html::a(Yii::t('core_system', 'Continue'), '/site/loginemail', ['class' => 'btn btn-primary mt-4']) . '
                        </div>';
            $_SESSION['message'] = $message;
            return $this->redirect(['/site/sysmes']);
        }
    }

    public function actionPwchange($id = null)
    {
        $model = $this->findModel(Yii::$app->user->identity->id);
        $model->scenario = 'pwchange';
        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        if ($model->load(Yii::$app->request->post())) {
            if ($model->temp_password == $model->retype_password && $model->retype_password !== $model->old_password) {
                $model->password = Yii::$app->getSecurity()->generatePasswordHash($model->temp_password);
                $model->old_password = $model->temp_password;
                if ($model->save()) {
                    $systemLog = new SystemLog();
                    $systemLog->user_id = $model->id;
                    $systemLog->instance = $model->instance;
                    $systemLog->message_short = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' changed the password';
                    $systemLog->message = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' changed the password for instance: ' . $model->instance . ' from ip: ' . Yii::$app->request->getUserIP();
                    $dataFormat = [
                        'event' => 'changePw',
                        'user' => $model->id,
                        'instance' => $model->instance,
                        'ip' => Yii::$app->request->getUserIP(),
                    ];
                    $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
                    $systemLog->save();
                    Yii::$app->session->setFlash('success', Yii::t('core_user', 'Your password has been changed successfully'));
                    return $this->redirect(['profile']);
                }
            }
            if ($model->temp_password == $model->retype_password && $model->old_password == $model->retype_password) {
                Yii::$app->session->setFlash('danger', Yii::t('core_user', 'Your new password can not be the same as the old password'));
                return $this->redirect(['pwchange']);
            }
        } else {
            return $this->render('pwchange', [
                'model' => $model,
            ]);
        }
    }

    public function actionAddemailpw($id = null)
    {
        $model = $this->findModel(Yii::$app->user->identity->id);
        $model->scenario = 'addemailpw';
        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        if ($model->load(Yii::$app->request->post())) {
            if ($model->temp_password === $model->retype_password) {
                $model->email_status = 'unverified';
                $model->email = strtolower($model->email);
                $model->password = Yii::$app->getSecurity()->generatePasswordHash($model->temp_password);
                if ($model->save()) {
                    $this->sendVerificationAddedEmail($model);
                    $systemLog = new SystemLog();
                    $systemLog->user_id = $model->id;
                    $systemLog->instance = $model->instance;
                    $systemLog->message_short = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' added email and password';
                    $systemLog->message = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' added email: ' . $model->email . ' and password for instance: ' . $model->instance . ' from ip: ' . Yii::$app->request->getUserIP();
                    $dataFormat = [
                        'event' => 'addEmailPw',
                        'user' => $model->id,
                        'email' => $model->email,
                        'instance' => $model->instance,
                        'ip' => Yii::$app->request->getUserIP(),
                    ];
                    $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
                    $systemLog->save();
                    Yii::$app->session->setFlash('success', Yii::t('core_user', 'Email and password has been added successfully'));
                    return $this->redirect(['profile']);
                }
            }
        }
        return $this->render('addemailpw', [
            'model' => $model,
        ]);
    }

    public function actionAddemail($id = null)
    {
        $model = $this->findModel(Yii::$app->user->identity->id);
        $model->scenario = 'addemail';
        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        if ($model->load(Yii::$app->request->post())) {
            $model->email = strtolower($model->email);
            $model->email_status = 'unverified';
            if ($model->save()) {
                $this->sendVerificationAddedEmail($model);
                $systemLog = new SystemLog();
                $systemLog->user_id = $model->id;
                $systemLog->instance = $model->instance;
                $systemLog->message_short = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' added email';
                $systemLog->message = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' added email: ' . $model->email . ' for instance: ' . $model->instance . ' from ip: ' . Yii::$app->request->getUserIP();
                $dataFormat = [
                    'event' => 'addEmail',
                    'user' => $model->id,
                    'email' => $model->email,
                    'instance' => $model->instance,
                    'ip' => Yii::$app->request->getUserIP(),
                ];
                $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
                $systemLog->save();
                Yii::$app->session->setFlash('success', Yii::t('core_user', 'Email has been added successfully'));
                return $this->redirect(['profile']);
            }
        }
        return $this->render('addemail', [
            'model' => $model,
        ]);
    }

    public function actionResendVerificationEmail($id = null)
    {
        $model = $this->findModel(Yii::$app->user->identity->id);
        $this->sendVerificationChangeEmailLink($model);
        $systemLog = new SystemLog();
        $systemLog->user_id = $model->id;
        $systemLog->instance = $model->instance;
        $systemLog->message_short = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' resend verification email';
        $systemLog->message = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' resend verification email: ' . $model->email . ' for instance: ' . $model->instance . ' from ip: ' . Yii::$app->request->getUserIP();
        $dataFormat = [
            'event' => 'resendEmail',
            'user' => $model->id,
            'email' => $model->email,
            'instance' => $model->instance,
            'ip' => Yii::$app->request->getUserIP(),
        ];
        $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
        $systemLog->save();
        Yii::$app->session->setFlash('success', Yii::t('core_user', 'Verification Email was sent'));
        return $this->redirect(['profile']);
    }

    public function actionEmailchange($id = null)
    {
        $model = $this->findModel(Yii::$app->user->identity->id);
        $model->scenario = 'echange';
        if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        if ($model->load(Yii::$app->request->post())) {
            if ($model->old_password) {
                $model->email = strtolower($model->email);
                $model->email_status = 'unverified';
                if ($model->save()) {
                    $this->sendVerificationChangeEmailLink($model);
                    $systemLog = new SystemLog();
                    $systemLog->user_id = $model->id;
                    $systemLog->instance = $model->instance;
                    $systemLog->message_short = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' changed email';
                    $systemLog->message = ($model->first_name ?? '') . ' ' . ($model->last_name ?? '') . ' changed email: ' . $model->email . ' for instance: ' . $model->instance . ' from ip: ' . Yii::$app->request->getUserIP();
                    $dataFormat = [
                        'event' => 'changeEmail',
                        'user' => $model->id,
                        'email' => $model->email,
                        'instance' => $model->instance,
                        'ip' => Yii::$app->request->getUserIP(),
                    ];
                    $systemLog->data_format = json_encode($dataFormat, JSON_THROW_ON_ERROR);
                    $systemLog->save();
                    Yii::$app->session->setFlash('success', Yii::t('core_user', 'Email has been changed successfully'));
                    return $this->redirect(['profile']);
                }
            }
        }
        return $this->render('emailchange', [
            'model' => $model,
        ]);
    }

    public function actionAddtagid()
    {
        $model = $this->findModel(Yii::$app->user->identity->id);
        return $this->render('addtagid', [
            'model' => $model,
        ]);
    }

    public function actionMergeaccounts()
    {
        $model = $this->findModel(Yii::$app->user->identity->id);
        $secondAccountLogin = new LoginForm();
        if (!isset($_SESSION['secondMergeAccount']) || $_SESSION['secondMergeAccount'] === null) {
            $secondAccount = null;
            if ($secondAccountLogin->load(Yii::$app->request->post())) {
                if ($secondAccountLogin->user) {
                    $secondAccount = $secondAccountLogin->user;
                    $_SESSION['secondAccount'] = $secondAccount->cid;
                }
            }
        } else {
            $secondAccount = User::findOne(['cid' => $_SESSION['secondMergeAccount']]);
            unset($_SESSION['secondMergeAccount']);
            $_SESSION['secondAccount'] = $secondAccount->cid;
        }
        return $this->render('mergeaccounts', [
            'model' => $model,
            'secondAccount' => $secondAccount,
            'secondAccountLogin' => $secondAccountLogin
        ]);
    }

    public function actionMergeAccountsConfirm(int $mergeInto)
    {
        if (isset($_SESSION['secondAccount'])) {
            $sessionAccount = User::findOne(['cid' => $_SESSION['secondAccount']]);
            if ($sessionAccount) {
                if ($mergeInto === 1) {
                    $mergeIntoAccount = $sessionAccount;
                    $mergeFromAccount = User::findOne(['id' => Yii::$app->user->identity->id]);
                } elseif ($mergeInto === 2) {
                    $mergeIntoAccount = User::findOne(['id' => Yii::$app->user->identity->id]);
                    $mergeFromAccount = $sessionAccount;
                }
                if (isset($mergeIntoAccount, $mergeFromAccount)) {
                    if (User::mergeAccounts($mergeIntoAccount, $mergeFromAccount)) {
                        Yii::$app->session->setFlash('success', Yii::t('core_user', 'Your accounts merged successfully'));
                        $this->redirect('/site/index');
                    }
                }
            }
        } else {
            $this->layout = Yii::$app->params['layout']['authentication'];
            $message = '<div class="site-index text-center">' .
                Yii::t('core_system', 'Something went wrong when merging your account<br>Please try again!') . '<br>' . Html::a(Yii::t('core_system', 'Go Back'), '/site/index', ['class' => 'btn btn-primary mt-4']) . '
                            </div>';
            $_SESSION['message'] = $message;
            return $this->redirect(['/site/sysmes']);
        }
    }

    protected function sendVerificationEmail($model)
    {
        $message = $this->getMailHeader();
        $message .= Yii::t('core_email', '<p><b>Dear {first_name} {last_name}</b>,</p><p style="margin-top:10px;">Your email address: {email} has been verified. You may now login to the site.<br><a href="{link}">go to login</a></p>', ['first_name' => $model->first_name, 'last_name' => $model->last_name, 'email' => $model->email, 'link' => Yii::$app->params['default_site_settings']['base_url'] . '/site/loginemail']);
        $message .= $this->getMailSignature();
        $subject = Yii::t('core_email', 'Email address verified');
        return $this->sendMail($message, $subject, $model->email);
    }

    private function sendVerificationLink(User $model)
    {
        $message = $this->getMailHeader();
        $message .= Yii::t('core_email', '<p><b>Dear {first_name} {last_name}</b>, <br>Thank you for registering to {site_name}. </p><p style="margin-top:10px;">In order to complete your registration you must verify your registered email address, please click on the verification link below.</p><a href="{link}">Verify your email address</a>', ['first_name' => $model->first_name, 'last_name' => $model->last_name, 'link' => Yii::$app->params['default_site_settings']['base_url'] . '/user/verify?cid=' . $model->cid, 'site_name' => (Yii::$app->params['default_site_settings']['site_name'] ?? 'SmartAdmin')]);
        $message .= $this->getMailSignature();
        $subject = Yii::t('core_email', 'Verify your email address');
        return $this->sendMail($message, $subject, $model->email);
    }

    private function sendVerificationChangeEmailLink(User $model)
    {
        $message = $this->getMailHeader();
        $message .= Yii::t('core_email', '<p><b>Dear {first_name} {last_name}</b>,</p> <p style="margin-top:10px;">Thank you for change your email.<br>Please click on the verification link below.<br><a href="{link}">Verify your email address</a></p>', ['first_name' => $model->first_name, 'last_name' => $model->last_name, 'link' => Yii::$app->params['default_site_settings']['base_url'] . '/user/verify?cid=' . $model->cid, 'site_name' => (Yii::$app->params['default_site_settings']['site_name'] ?? 'SmartAdmin')]);
        $message .= $this->getMailSignature();
        $subject = Yii::t('core_email', 'Verify your email address');
        return $this->sendMail($message, $subject, $model->email);
    }

    private function sendRepeatVerificationLink(User $model)
    {
        $message = $this->getMailHeader();
        $message .= Yii::t('core_email', '<p><b>Dear {first_name} {last_name}</b>,</p> <p style="margin-top:10px;">Thank you for your patience, we hope this link will work better for you.<br>If the problem persists, please do not hesitate to contact our support.<br><a href="{link}">Verify your email address</a></p>', ['first_name' => $model->first_name, 'last_name' => $model->last_name, 'link' => Yii::$app->params['default_site_settings']['base_url'] . '/user/verify?cid=' . $model->cid, 'site_name' => (Yii::$app->params['default_site_settings']['site_name'] ?? 'SmartAdmin')]);
        $message .= $this->getMailSignature();
        $subject = Yii::t('core_email', 'Verify your email address');
        return $this->sendMail($message, $subject, $model->email);
    }

    private function sendVerificationAddedEmail(User $model)
    {
        $message = $this->getMailHeader();
        $message .= Yii::t('core_email', '<p><b>Dear {first_name} {last_name}</b>, <br>Thank you for adding your email address to your account. </p><p style="margin-top:10px;">In order for your email to be added correctly to your account it has to be verified, please click on the verification link below.<br><a href="{link}">Verify your email address</a></p>', ['first_name' => $model->first_name, 'last_name' => $model->last_name, 'link' => Yii::$app->params['default_site_settings']['base_url'] . '/user/verify?cid=' . $model->cid]);
        $message .= $this->getMailSignature();
        $subject = Yii::t('core_email', 'Verify your email address');
        return $this->sendMail($message, $subject, $model->email);
    }

    protected function sendPasswordReset($model, $hash)
    {
        $message = $this->getMailHeader();
        $message .= Yii::t('core_email', '<p><b>Dear {first_name} {last_name}</b>,<br>You have initiated a password reset request.</p><p style="margin-top:10px;">In order to complete, you must click on the following link then type and save the new password on the page that loads.<br><a href="{link}">Reset your password</a></p>', ['first_name' => $model->first_name, 'last_name' => $model->last_name, 'link' => Yii::$app->params['default_site_settings']['base_url'] . '/user/resetpw?id=' . $model->id . '&hash=' . $hash]);
        $message .= $this->getMailSignature();
        $subject = Yii::t('core_email', 'Password reset requested');
        return $this->sendMail($message, $subject, $model->email);
    }

    private function getMailHeader()
    {
        return '<h2 style="text-align: center"><img src="' . Yii::$app->thumbnailer->get(Yii::$app->params['default_site_settings']['base_url'] . Yii::$app->params['branding']['lightLogo'], 30, 30, 100, ManipulatorInterface::THUMBNAIL_OUTBOUND, true) . '" style="max-height:30px"> ' . (Yii::$app->params['default_site_settings']['site_name'] ?? 'SmartAdmin') . '</h2>';
    }

    private function getMailSignature()
    {
        return '<table style="width: 100%; margin-top:30px">
            <tr>
                <td><p><i>This is an automatically generated message, please do not reply to this email.<br>If you wish to send us a message, please, use the contact form on the website</i><p></td>
            </tr>
            <tr><td style="height:20px;"></td></tr>
            <tr>
                <th><b>Kind regards,</b></th>
            </tr>
            <tr>
                <td>Administration</td>
            </tr>
            <tr>
                <td>' . (Yii::$app->params['default_site_settings']['site_name'] ?? 'SmartAdmin') . '</td>
            </tr>
        </table>';
    }

    private function sendMail($message, $subject, $email)
    {
        Yii::$app->mailer->compose()
            ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
            ->setReplyTo([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
            ->setTo($email)
            ->setSubject($subject)
            ->setTextBody($message)
            ->setHtmlBody($message)
            ->send();
        return 'OK';
    }


    /**
     * @throws ServerErrorHttpException
     * @throws \JsonException
     * @throws NotFoundHttpException
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
                $timeNowUTC =  $timeNow->getTimestamp();

                if (($timeNowUTC - $sessionLogged) >= (Yii::$app->params['systemTimeout']['authTimeout'])) {

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
        } else {
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
            $timeNowUTC =  $timeNow->getTimestamp();
            $sessionTimeout = Yii::$app->params['systemTimeout']['authTimeout'];

            if (($timeNowUTC - $userLoginSession->session_logged) > $sessionTimeout) {

                $userLoginSession->expire = $timeNowUTC + $sessionTimeout;
                $userLoginSession->save();
            }

            echo json_encode([
                'renewSession' => self::LOGIN_SESSION_RENEW, // 'renewSession'
                'renewCounterValue' => Yii::$app->params['systemTimeout']['authTimeout'],
            ], JSON_THROW_ON_ERROR);

        } else {
            echo json_encode([
                'renewSession' => self::LOGIN_SESSION_RENEW, // 'renewSession'
                'renewCounterValue' => 0,
            ], JSON_THROW_ON_ERROR);

        }
    }
}
