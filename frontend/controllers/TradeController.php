<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */

namespace yuncms\payment\frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yuncms\payment\models\Trade;

/**
 * Class TradeController
 * @package yuncms\payment\frontend\controllers
 */
class TradeController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    //已认证用户
                    [
                        'allow' => true,
                        'actions' => ['create', 'pay', 'query'],
                        'roles' => ['@']
                    ],
                    [
                        'allow' => true,
                        'actions' => ['return'],
                        'roles' => ['@', '?']
                    ],
                ]
            ],
        ];
    }

    /**
     * 支付默认表单
     * @return string
     */
    public function actionCreate()
    {
        $model = new Trade();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['/payment/trade/pay', 'id' => $model->id]);
        }
        return $this->render('create', ['model' => $model]);
    }

    /**
     * 去付款
     * @param int $id
     * @return string
     * @throws NotFoundHttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionPay($id)
    {
        $payment = $this->findModel($id);
        $paymentParams = [];
        Yii::$app->payment->get($payment->gateway)->payment($payment, $paymentParams);
        if ($paymentParams) {
            if (Yii::$app->request->isAjax) {
                return $this->renderPartial('pay', ['payment' => $payment, 'paymentParams' => $paymentParams]);
            } else {
                return $this->render('pay', ['payment' => $payment, 'paymentParams' => $paymentParams]);
            }
        }
        return $this->redirect(['/payment/trade/create', 'id' => $payment->id]);
    }

    /**
     * 交易查询
     * @param string $id
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionQuery($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $payment = $this->findModel($id);
        if ($payment->state == Trade::STATE_SUCCESS) {
            return ['state' => 'success'];
        } else {
            return ['state' => 'pending'];
        }
    }

    /**
     * 获取支付单号
     * @param int $id
     * @return Trade
     * @throws NotFoundHttpException
     */
    public function findModel($id)
    {
        if (($model = Trade::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}