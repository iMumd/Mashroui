<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** مرفق مراسلة من الإدارة — يُخزَّن مرة واحدة ويشترك فيه كل مستلمي نفس الـbatch */
#[Fillable(['batch', 'name', 'path', 'size'])]
class AnnouncementAttachment extends Model
{
    protected $hidden = ['path'];
}
