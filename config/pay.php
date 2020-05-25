<?php
return [
    'alipay' => [
        'app_id'         => '2016102300742225',
        'ali_public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtAzKwE1BcgumLUMPo9DRT4iz8zu0YN46GUfqt+Y47R8zWBHr80kY6N4otx4P3FhnKvY9E5VUFgeQqSmHSVy2h06kistr5mDlOoRPfXo9LVz2rtcSQTj8qWUopOVIFpDz+SDhjIlXOZAja6UJ3yhiAF2A+BykiOpH0Rw/VhS1AHDoi3p1uzkdp2gUHfVHlzJfQYk8pN60Rz8hO+cdgme6XwWfXMEQ/vNCmATHbRjIlOMjGSZ5fJehPa/krEc/j7tljqSTbIByP8ZwLa6/rVFl9Ut0ch17cZHwnYX8o0qb2KS0XIS6yXbPvFniWuueG+2v1RPS7LhtuHObZkYOtNfqJwIDAQAB',
        'private_key'    => 'MIIEpQIBAAKCAQEA5ZPNwRzIkzoqYKdtwhy54FZXxnvF1DuTbs/3GFjQRtlGYat0f1ZoYNFTEfTxQpFxEpFiCWXNiyWAemfLz7Xc0poKKyrcnDppJ+MDAekcF9lwQwX/hG2ClTlz8iUB9N92FMvMr0fFZdark4Q6KC1fd1iUpUmY5FVt+XbIudxV6rRNFA7O7XCAJUY3qJDFDo4oDvPyxq+lQAZFU7eRRabZYff2faAYn4uuYoy7kMx2mqhSgnE2yeN/6wNSNM6vAcVyn1uQjD7B8RsixIMsVkcr+MGG/s712wgFgUMGTdrRXdypyXISbMpZw6W3TkvnPe3yb+dQ0VjWiVGx1yiSb4BJ4wIDAQABAoIBABw9yGvfHJVv7FKjhg8A9aSxOm8CPt3SzlotXxoxor10cQIG03fuqzxtw6hL13n25lAAbmz1hwl8/YcRSbMtjhKiPGn/BdB6JwFvHamYgYo1h2fXTw19Yzj0Ll01TD5RVvdL8swFKjVS6Rxmgbj7i+jkjrSejx5XnAW6JQsoHbGXbNXp5Qk+SNFX9iXdM5nE306pRtoxjLUCj2VpA4lOd5yY5dspiOJOPXmMET6Io3FWbThJCzcOEuZm1RxxGLNrC/P4ynGl1SM/+FjPWbjt5R2mAfP9yKvrY6dLLT/+VI6ao7r1adv5mNkVKvwZRQSYbgiAbeeBwuYglbpm3G7FXGECgYEA9OhpoBCgC48jfXVm+NUpqh8BUnvlnieOScaCv6eF2GglBLOIRV3Ywe2j8KBNBJvqbVNoiUeKqYrXxYL7UeOFprqAadqO1s1vbPp7Saw17aw5yp8jpbnz0HSotm1K+r/BbigynH3jhBP9Uzu7MWvnq70EylurmynMXQImlMtCJUsCgYEA7/mkM0WfrfIiPET3U3cLKnB4Ek40sTcaS7MLQTDU9K9gbO3eCBmnPsUB2mqQRo52z/MS+13aRIvvfJYxci+4zH8oC8C60SFZarK7qLx/LIgAWY46kMoigpIR4UXewtZF1ge2k0sRnON6ykemVLijkKfiIZx9lpLqP/hc6FCLxskCgYEA5z/VDJjmJkQAJOSIWIkekE46d2IZzJ5ZEnxo1QMftNmRqsDC+YzFsUfstrcGQkXes2BAou90lOZPG2a0W/xUJLyRqGIA4f6VKffSFhyPx14KK7LcfA37lGezLp5AYEhUIum98FpGTF57ou4RsXbMCDOwpEqu3RI4r9HqEDFkwcUCgYEAzCJ7KFZQmAKCVux/y5NXfzl/GmtGbX2nM7/Rkhn8/Uv2ZZMOratM1pt1kRvXMP3dWdW3TvauRmMXTM7irvSjaem84gqHoICS9K2n7siyEep7CbNwZFxecN3asdviu7DUZVXYXIGeMOxdNpcuiaErGlGFjfv15VGCTPpz5fcmf+kCgYEA8bWhktY1fe1KrM2ivxwkgiZarslJP+oEZK47u0XI7soY0JPUz5JGp/YddIxycIy2dJPALZpUz02JJhZQRa2K5gKwEt1ySycAb8A2os2pkdAf1DboFRw4zBNWXI1kQQ7Tw+t35ghKgbxpYTpZjlndukp8kQD4jvpdrwAdcrgIaUM=',
        'log'            => [
            'file' => storage_path('logs/alipay.log'),
        ],
    ],

    'wechat' => [
        'app_id'      => '',
        'mch_id'      => '',
        'key'         => '',
        'cert_client' => '',
        'cert_key'    => '',
        'log'         => [
            'file' => storage_path('logs/wechat_pay.log'),
        ],
    ],
];
