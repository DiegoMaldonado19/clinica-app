<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $question
 * @property string $answer
 */
#[Fillable(['question', 'answer', 'position'])]
class Faq extends Model {}
