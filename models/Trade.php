<?php

namespace yuncms\payment\models;

use Yii;
use yii\db\Query;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;
use yii\behaviors\AttributeBehavior;
use yii\behaviors\TimestampBehavior;
use yuncms\user\models\User;
use xutl\payment\OrderInterface;

/**
 * This is the model class for table "{{%trade}}".
 *
 * @property string $id 交易号
 * @property string $outTradeNo 交易号
 * @property integer $user_id 用户ID
 * @property string $gateway 网关
 * @property integer $type 交易类别
 * @property string $pay_id 交易平台的交易号
 * @property string $currency 币种
 * @property string $subject 订单标题
 * @property string $total_amount 总钱数
 * @property string $discountable_amount 可优惠的钱数
 * @property string $body 交易描述
 * @property integer $model_id 发起收款的模型ID
 * @property string $model_class 发起收款的模型类名
 * @property integer $state 交易状态
 * @property string $ip 发起交易的用户IP
 * @property string $note 备注描述
 * @property string $return_url 回跳Url
 * @property integer $created_at 创建时间
 * @property integer $updated_at 更新时间
 *
 * @property User $user
 *
 * @property-read boolean $isAuthor 是否是作者
 * @property-read boolean $isSuccess 是否已支付
 */
class Trade extends ActiveRecord
{
    //交易类型
    const TYPE_NATIVE = 0b1;//原生扫码支付
    const TYPE_JS_API = 0b10;//应用内JS API,如微信
    const TYPE_APP = 0b11;//app支付
    const TYPE_H5 = 0b100;//H5支付
    const TYPE_MICROPAY = 0b101;//刷卡支付
    const TYPE_OFFLINE = 0b110;//离线（汇款、转账等）支付

    //交易状态
    const STATE_NOT_PAY = 0b0;//未支付
    const STATE_SUCCESS = 0b1;//支付成功
    const STATE_FAILED = 0b10;//支付失败
    const STATE_CLOSED = 0b100;//已关闭
    const STATE_REVOKED = 0b101;//已撤销
    const STATE_ERROR = 0b110;//错误
    const STATE_REFUND = 0b111;//转入退款
    const STATE_REFUND_SUCCESS = 0b11;//转入退款
    const STATE_REFUND_FAILED = 0b11;//转入退款

    //场景定义
    const SCENARIO_CREATE = 'create';//创建
    const SCENARIO_UPDATE = 'update';//更新

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%trade}}';
    }

    /**
     * 定义行为
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
            [
                'class' => 'yii\behaviors\BlameableBehavior',
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => ['user_id'],
                ],
            ],
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'id',
                ],
                'value' => function ($event) {
                    return $event->sender->generateOutTradeNo();
                }
            ],
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'ip',
                ],
                'value' => function ($event) {
                    return Yii::$app->request->userIP;
                }
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        return ArrayHelper::merge($scenarios, [
            static::SCENARIO_CREATE => ['type', 'currency', 'subject', 'total_amount'],
            static::SCENARIO_UPDATE => [],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'currency', 'subject', 'total_amount'], 'required'],

            ['id', 'unique', 'message' => Yii::t('payment', 'This id has already been taken')],

            ['type', 'default', 'value' => static::TYPE_NATIVE],
            ['type', 'in', 'range' => [static::TYPE_NATIVE, static::TYPE_JS_API, static::TYPE_APP, static::TYPE_H5, static::TYPE_MICROPAY, static::TYPE_OFFLINE,]],

            [['total_amount', 'discountable_amount'], 'number'],
            ['discountable_amount', 'default', 'value' => 0.00],

            ['state', 'default', 'value' => static::STATE_NOT_PAY],
            ['state', 'in', 'range' => [static::STATE_NOT_PAY, static::STATE_SUCCESS, static::STATE_FAILED, static::STATE_REFUND, static::STATE_CLOSED, static::STATE_REVOKED, static::STATE_ERROR,]],

            [['user_id', 'type', 'model_id', 'state'], 'integer'],

            [['note', 'return_url'], 'string'],

            [['gateway'], 'string', 'max' => 50],

            [['pay_id', 'subject', 'model_class'], 'string', 'max' => 255],
            [['currency'], 'string', 'max' => 20],
            [['body'], 'string', 'max' => 128],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],


        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('payment', 'ID'),
            'user_id' => Yii::t('payment', 'User ID'),
            'gateway' => Yii::t('payment', 'Gateway'),
            'type' => Yii::t('payment', 'Trade Type'),
            'pay_id' => Yii::t('payment', 'Trade No'),
            'currency' => Yii::t('payment', 'Currency'),
            'subject' => Yii::t('payment', 'Subject'),
            'total_amount' => Yii::t('payment', 'Total Amount'),
            'discountable_amount' => Yii::t('payment', 'Discountable Amount'),
            'body' => Yii::t('payment', 'Body'),
            'model_id' => Yii::t('payment', 'Model ID'),
            'model_class' => Yii::t('payment', 'Model Class'),
            'state' => Yii::t('payment', 'Trade State'),
            'ip' => Yii::t('payment', 'IP'),
            'note' => Yii::t('payment', 'Note'),
            'return_url' => Yii::t('payment', 'Return Url'),
            'created_at' => Yii::t('payment', 'Created At'),
            'updated_at' => Yii::t('payment', 'Updated At'),
        ];
    }

    /**
     * 商户订单号
     * @return int
     */
    public function getOutTradeNo()
    {
        return $this->id;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    /**
     * @inheritdoc
     * @return TradeQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new TradeQuery(get_called_class());
    }

    /**
     * 是否是作者
     * @return bool
     */
    public function getIsAuthor()
    {
        return $this->user_id == Yii::$app->user->id;
    }

    /**
     * 是否已支付
     * @return bool
     */
    public function getIsSuccess()
    {
        return $this->state == self::STATE_SUCCESS;
    }

    /**
     * 获取状态列表
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATE_NOT_PAY => Yii::t('payment', 'Not Pay'),
            self::STATE_SUCCESS => Yii::t('payment', 'State Success'),
            self::STATE_FAILED => Yii::t('payment', 'State Failed'),
            self::STATE_CLOSED => Yii::t('payment', 'State Close'),
        ];
    }

//    public function afterFind()
//    {
//        parent::afterFind();
//        // ...custom code here...
//    }

    /**
     * @inheritdoc
     */
//    public function beforeSave($insert)
//    {
//        if (!parent::beforeSave($insert)) {
//            return false;
//        }
//
//        // ...custom code here...
//        return true;
//    }

    /**
     * @inheritdoc
     */
//    public function afterSave($insert, $changedAttributes)
//    {
//        parent::afterSave($insert, $changedAttributes);
//        Yii::$app->queue->push(new ScanTextJob([
//            'modelId' => $this->getPrimaryKey(),
//            'modelClass' => get_class($this),
//            'scenario' => $this->isNewRecord ? 'new' : 'edit',
//            'category'=>'',
//        ]));
//        // ...custom code here...
//    }

    /**
     * @inheritdoc
     */
//    public function beforeDelete()
//    {
//        if (!parent::beforeDelete()) {
//            return false;
//        }
//        // ...custom code here...
//        return true;
//    }

    /**
     * @inheritdoc
     */
//    public function afterDelete()
//    {
//        parent::afterDelete();
//
//        // ...custom code here...
//    }

    /**
     * 生成交易流水号
     * @return string
     */
    protected function generateOutTradeNo()
    {
        $i = rand(0, 9999);
        do {
            if (9999 == $i) {
                $i = 0;
            }
            $i++;
            $id = time() . str_pad($i, 4, '0', STR_PAD_LEFT);
            $row = (new Query())->from(static::tableName())->where(['id' => $id])->exists();
        } while ($row);
        return $id;
    }

    /**
     * 设置支付状态
     * @param string $paymentId
     * @param int $status
     * @param array $params
     * @return bool
     */
    public static function setPayStatus($paymentId, $status, $params)
    {
        if (($payment = static::findOne(['id' => $paymentId])) == null) {
            return false;
        }
        if (static::STATE_SUCCESS == $payment->trade_state) {
            return true;
        }
        if ($status == true) {
            $payment->updateAttributes([
                'pay_id' => $params['pay_id'],
                'trade_state' => static::STATE_SUCCESS,
                'note' => $params['message']
            ]);//标记支付已经完成
            /** @var \yuncms\payment\OrderInterface $orderModel */
            $orderModel = $payment->model;
            if (!empty($payment->model_id) && !empty($orderModel)) {
                $orderModel::setPayStatus($payment->model_id, $paymentId, $status, $params);
            }
            return true;
        }
        return false;
    }
}
