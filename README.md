Yii2 Relational behavior
------------------------

This behavior allows you to set relations in your code. Also, it saves many-to-many relations declared with `via()` or `viaTable()`

Relations accepts `int|int[]|ActiveRecord|ActiveRecord[]`.

## Install
```
php composer.phar require e96/yii2-relational-behavior:dev-master
```

## Setup
In model:
```php
class User extends ActiveRecord
{
    public function behaviors()
    {
        return [
            e96\behavior\RelationalBehavior::className(), // enable behavior
        ];
    }

    public function rules()
    {
        return [
            ['permissions', 'safe'], // allow set permissions with setAttributes()
        ];
    }

    // define many-to-many relation
    public function getPermissions()
    {
        return $this->hasMany(Permission::className(), ['id' => 'permissionId'])
            ->viaTable('user-map-permission', ['userId' => 'id']);
    }
}
```

In model with sorting support:
```php
class User extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'relationalBehavior' => [
                'class' => \petrixs\behavior\RelationalBehavior::class,
                'sortSettings' => [
                    'relation' => [
                       'sortColumn' => 'Sort column name in linking table'
                    ]
                ]
            ]
        ];
    }

    public function rules()
    {
        return [
            ['permissions', 'safe'], // allow set permissions with setAttributes()
        ];
    }

    // define many-to-many relation
    public function getPermissions()
    {
        return $this->hasMany(Permission::className(), ['id' => 'permissionId'])
            ->viaTable('user-map-permission', ['userId' => 'id']);
    }
}

```

In view:
```php
$form->field($model, 'permissions')->dropDownList($permissions, ['multiple' => true])
```

## Usage
```php
$user->load(Yii::$app->request->post());
$user->save();
```

Other usages:
```php
$user = User::findOne(1);
$user->permissions = 1;
// or
$user->permissions = [1,2];
// or
$user->permissions = Permission::findOne(1);
// or
$user->permissions = Permission::find()->all();
// or
$user->load(Yii::$app->request->post());
// or
$user->setAttributes(['permissions' => [1,2]]);
// then
$user->save();
```


> Written with [StackEdit](https://stackedit.io/).
