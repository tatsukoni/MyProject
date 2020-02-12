<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Request;
use Carbon\Carbon;

class ActionStoresRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'target_data' => 'required|int',
            'user_ids' => 'required|string'
        ];
    }
}
