<?php

declare(strict_types=1);

namespace App\Docs;

final class OpenApiSchemas
{
    public const DEVICE = 'array{id:int, user_id:int|null, wallet_user_id:int, platform:string|null, device_name:string|null, device_type:string|null, fcm_token:string|null, expired_at:string|null}';

    public const NOTIFY = 'array{id:int, name:string, wallet_id:int, notify_enable:bool, wallets:array{id:int|null, code:string|null}}';

    public const SOCIAL = 'array{id:int, social_type:int, social_type_value:string, name:string, email:string, image:string}';

    public const SOCIAL_CHECK_BIND = 'array{action:string, token:string}';

    public const OPTION_EXCHANGE_RATE = 'array{option:array<string,mixed>, rates:array<string,mixed>, updated_at:string|null}';

    public const WALLET_AUTH_LOGIN_DATA = 'array{id:int, name:string, wallet_id:int, member_token:string, jwt:string|null, wallet:array{id:int, code:string}, devices:array<int,'.self::DEVICE.'>, notifies:array<int,'.self::NOTIFY.'>}';

    public const WALLET_AUTH_TOKEN_LOGIN_DATA = 'array{id:int, name:string, wallet_id:int, member_token:string, wallet:array{id:int, code:string}}';

    public const WALLET_USER = 'array{id:int, wallet_id:int, user_id:int|null, name:string, is_admin:bool, notify_enable:bool, created_at:string, updated_at:string}';

    public const WALLET = 'array{id:int, user_id:int, code:string, title:string, mode:string, unit:string, status:int, properties:mixed, created_at:string, updated_at:string}';

    public const WALLET_DETAIL = 'array{id:int, wallet_id:int, type:int, title:string, symbol_operation_type_id:int, value:float|int, unit:string, date:string, note:string|null, category_id:int|null, payment_wallet_user_id:int|null, select_all:bool, is_personal:bool, users:array<int,int>, created_by:int|null, checkout_at:string|null, created_at:string, updated_at:string}';

    public const PAGINATE = 'array{current_page:int, per_page:int, total_count:int, total_page:int}';

    public const WALLET_INDEX_DATA = 'array{paginate:'.self::PAGINATE.', wallets:array<int,'.self::WALLET.'>}';

    public const WALLET_INDEX_RESPONSE = 'array{status:true, code:200, message:string, data:'.self::WALLET_INDEX_DATA.'}';

    public const WALLET_DETAIL_INDEX_DATA = 'array{wallet:array{id:int, code:string, title:string, status:int, mode:string, unit:string, wallet_user:'.self::WALLET_USER.'|null, properties:mixed, created_at:string, updated_at:string, details:array<int,'.self::WALLET_DETAIL.'>, wallet_users:array<int,'.self::WALLET_USER.'>, total:array{income:float, expenses:float}}}';

    public const WALLET_DETAIL_INDEX_RESPONSE = 'array{status:true, code:200, message:string, data:'.self::WALLET_DETAIL_INDEX_DATA.'}';

    public const AUTH_LOGIN_DATA = 'array{id:int|string|null, name:string|null, member_token:string|null, jwt:string|null, wallet:'.self::WALLET.'|object, walletUsers:array<int,'.self::WALLET_USER.'>, devices:array<int,'.self::DEVICE.'>, notifies:array<int,'.self::NOTIFY.'>}';

    public const AUTH_LOGIN_RESPONSE = 'array{status:bool, code:int, message:string, data:'.self::AUTH_LOGIN_DATA.'}';
}
