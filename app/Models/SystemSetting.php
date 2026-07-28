<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;
    use HasShortId;

    public const GROUP_FORMS = 'forms';

    public const GROUP_GENERAL = 'general';

    public const KEY_FORMS_LOGO = 'forms.logo';

    public const TYPE_STRING = 'string';

    public const TYPE_FILE = 'file';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_JSON = 'json';

    public const TYPE_TEXT = 'text';

    protected $fillable = [
        'key',
        'group',
        'type',
        'value',
        'label',
        'description',
    ];
}
