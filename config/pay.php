<?php

return [
    'alipay' => [
        // 支付宝分配的 APPID
        'app_id' => '2021000121660775',

        // 支付宝异步通知地址
        'notify_url' => 'https://f174-115-237-163-249.ngrok.io/api/alipay/notify',

        // 支付成功后同步通知地址
        'return_url' => '',

        // 阿里公共密钥，验证签名时使用
        'ali_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuwCISQQVf/loTs9zWx8lDr9JSmfIEp+GN8pNq3h5aurvfsHbBhNcOlTguO1ptk4PPGV2Q+c/xlNgGetROkPvZAgdQ7TZXCswhkGk7omKjpt+A/x7NtvzDc3n/bRQ3We3lYg1dty3TTjXmD491r1GhvE++x36H3DHEN4FjwL2ixuYi2ohXeB1nEi2oEq5F6/KK3yfe4Frgx+7i8HKCHh3DCKeQuSiM56S+cLVetqEuX7rHsYsuAFfomg6dzYOWzdfr7TzPbKVAxEFPzbHxbsvrRGTjqPT3lBW3hBX1wCsreSqgeu6wVCJqa1i8EJ3hofYVvpfB5LStoDgwn8tB9p6mwIDAQAB',

        // 自己的私钥，签名时使用
        'private_key' => 'MIIEowIBAAKCAQEA30/5d+SSusJA690BJrFJO3RpSq0Ux8S7imxdycKVIY1alKOdd3a85zsQp3kucq2bG57M0oKXPVIm6jOvLDKFzZRUY7V5dwxbY2eZbg6nLpBWHgzLCYya6B6Bs41XYgZ9CCzQNnYsO3ppGvpJhWnsfWGqizSwsOsfG9wcacdGm3oDCtafcunIJRe0Nz6d0ubF5BmfOY9L/Fp+7K/va7GHbevVhHVsa1wVvdsono9nFiW12mbttZ9hQcwYjMIltmLVABx+pBlRLyDyuqBV+vRH5bXmDVB/CewkXQgZUmQ7/M9EW8MtdhutWS2LaJDdVoswHf7ugFv+LFEBw2egHu5yZQIDAQABAoIBAEzsMIPTEZQRe+mDXHUmlpJLXEWj70eNBgj9oSTxyQsgtPYEkiZnmVSRbQAzZwOLt6VBA070OwKdfNgp64pu8NZK5PLfvTJ76DMKqbhWhdItV+zL/ViRvX59m7Hs2w/iKkmZnjNUa5IlbXUkiBTT0umxrdx7zu1yYGnpXUQ2p6TAMpbkyNqGdCiRz0Cgc7927V/lNjqOareuvE8yoBBiJ0q7FwYuf8LA4oY/I6SgoUVn00qGv1Gy6GL0xshvPmf+YEWnERTTgcJ8ez5zr+v9OMMie739IaFq+PCBJcU9FzMiwt7Rsk0gGUyp0BnjGcJW+PxqHfuPEOI/pKb2LxnBHTUCgYEA//k91s+5QRjeFriWZ1tpM5a7nQfmmj0PVFkC9tTat/0HqOr34cQP0e3rkfqACVTmdM/T9Ila593FdL+4aQr/+rIcFAAg0jBB8EYxf9NN6AZvm7z/pnbZPddIsF2TpMIqiyt/Xf4F89UGUWPKE4aJoxj/dtwMLGK4w4dZZflk+U8CgYEA31Xe3h+KT0nwBVGSmxlCTJvVTcE/8Z/MH+gdJ4xHdDk4EfV5b+wc5+8rhX9Zc5KU/NyCvHnuspdTfmwGM2KQMsR9PRyZRIPxatzdFF6ixW3QuALmMqRH9MhhYxm6XumEh3TGTFXpuIJ5QFmKYmAmzIqsIv4Z53ovqr1va9+FhAsCgYEAnBhE0qMXyI++l0J3TY4b6D7KKCdyQ6pql7g0K4t/2WUu755iDUX/k7gvyHRm8cnZQ3CdQ2kji3Pc/qYPcdcoZJuKrdOqMCMHgtt7QgT8ZDrQgrtNdxjQv3pyNELMT1Osl/OtlwbaYGgOk0F0MFYBxvjuiHAF6GdHXs1CAgKExdcCgYAhTd/fRdrNOhxl3qU7JcgiPnbPkorjkE3TUDzQwfWB2mqHGxER5Kbm216lGLmRJ4G6N3PzgUdcMH7N8nP2Q7N3Lp/ydgpREk/0/JynRfmF8XtXhW8ojCEC2sLTEWoebzpJLNvJVGJ3FlXYh8HAK9B9XgyPZ08bmrfzAS7Vak6xwwKBgANMH6fE6tP5oqguf6YHxzOCWIrnB5Ma54epGjKN99f7eeyvNoFC6X2Fcu/vkpp+qdVBoYZH0/FVdz8zFz1Ahw8brOaMWTDyE6qaskDmm1142qmBnjiAc/ZGthzt3Y9VKjs46yzy/WNr7U9km0wjeLrCBKuLsD/sdNTYpjtZjQ1c',

        // optional，默认 warning；日志路径为：sys_get_temp_dir().'/logs/yansongda.pay.log'
        'log' => [
            'file' => storage_path('logs/alipay.log'),
        //  'level' => 'debug'
        //  'type' => 'single', // optional, 可选 daily.
        //  'max_file' => 30,
        ],

        // optional，设置此参数，将进入沙箱模式
        // 'mode' => 'dev',
    ],

    'wechat' => [
        // 公众号 APPID
        'app_id' => env('WECHAT_APP_ID', 'wxd705ad5d6ff8e6fc'),

        // 小程序 APPID
        'miniapp_id' => env('WECHAT_MINIAPP_ID', ''),

        // APP 引用的 appid
        'appid' => env('WECHAT_APPID', 'wxd705ad5d6ff8e6fc'),

        // 微信支付分配的微信商户号
        'mch_id' => env('WECHAT_MCH_ID', '1572236741'),

        // 微信支付异步通知地址
        'notify_url' => 'https://f174-115-237-163-249.ngrok.io/api/wechat/pay/notify',

        // 微信支付签名秘钥
        'key' => env('WECHAT_KEY', 'FnSXkd6Y9SGwLn7DUadT2dGWyCXGKQhA'),

        // 客户端证书路径，退款、红包等需要用到。请填写绝对路径，linux 请确保权限问题。pem 格式。
        'cert_client' => '',

        // 客户端秘钥路径，退款、红包等需要用到。请填写绝对路径，linux 请确保权限问题。pem 格式。
        'cert_key' => '',

        // optional，默认 warning；日志路径为：sys_get_temp_dir().'/logs/yansongda.pay.log'
        'log' => [
            'file' => storage_path('logs/wechat.log'),
        //  'level' => 'debug'
        //  'type' => 'single', // optional, 可选 daily.
        //  'max_file' => 30,
        ],

        // optional
        // 'dev' 时为沙箱模式
        // 'hk' 时为东南亚节点
        // 'mode' => 'dev',
    ],
];
