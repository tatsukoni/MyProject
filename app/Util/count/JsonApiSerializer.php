<?php

/*
 * This file is part of the League\Fractal package.
 *
 * (c) Phil Sturgeon <me@philsturgeon.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Serializers;

use InvalidArgumentException;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Serializer\ArraySerializer;
use League\Fractal\Serializer\JsonApiSerializer as ParentSerializer;

use App\Models\Group;
use App\Models\User;

class JsonApiSerializer extends ParentSerializer
{

    /**
     * Serialize a collection.
     *
     * @param string $resourceKey
     * @param array  $data
     *
     * @return array
     */
    public function collection($resourceKey, array $data)
    {
        $resources = [];
        foreach ($data as $resource) {
            $resources[] = $this->item($resourceKey, $resource)['data'];
        }
        return [
            'links' => [
                'self' => request()->fullUrl()
            ],
            'data' => $resources,
            'auth' => $this->getUser()
        ];
    }

    /**
     * Serialize an item.
     *
     * @param string $resourceKey
     * @param array  $data
     *
     * @return array
     */
    public function item($resourceKey, array $data)
    {
        // if (is_null($id)) {
        //     $id = $this->getIdFromData($data);
        // } else {
        //     $id = '';
        // }
        $id = $this->getIdFromData($data);
        $resource = [];
        if (in_array(request()->method(), ['GET', 'HEAD', 'PUT', 'PATCH', 'POST']) && $this->shouldIncludeLinks()) {
            $resource['links'] = [
                'self' => request()->fullUrl(),
            ];
        }
        $resource['data'] = [
            'type' => $resourceKey,
            'id' => $id,
            'attributes' => $data,
        ];
        unset($resource['data']['attributes']['id']);

        $resource['auth'] = $this->getUser();
        return $resource;
    }

    /**
     * Get authorized user information
     *
     * ログインしているユーザ情報のセット
     */
    public function getUser()
    {
        $auth = auth('api')->user();
        if (empty($auth)) {
            // トークン発行時はユーザ情報が取得できない
            return [];
        }

        // セットするカラム
        $columns = [
            'id',
            'username',
            'view_mode',
            'deferrable'
        ];

        $user = [];
        array_walk(
            $columns,
            function ($column) use ($auth, &$user) {
                $user[$column] = $auth->{$column};
            }
        );

        // Set user status (is_xxx)
        // For admin
        $user['is_admin_group'] = in_array($auth->group_id, Group::$adminGroups);
        $user['is_backend_user'] = $auth->group_id == Group::GROUP_ID_BACKEND_USER;
        $user['is_owner'] = $auth->group_id == Group::GROUP_ID_OWNER;
        $user['is_finance'] = $auth->group_id == Group::GROUP_ID_FINANCE;
        $user['is_support'] = $auth->group_id == Group::GROUP_ID_SUPPORT;
        $user['is_support_admin'] = $auth->group_id == Group::GROUP_ID_SUPPORT_ADMIN;
        $user['is_realtime'] = $auth->group_id == Group::GROUP_ID_REALTIME;

        // For user
        $user['email'] = $auth->email;
        $user['is_pre_user'] = $auth->group_id == Group::GROUP_ID_PRE_USER;
        $user['is_limited_group'] = in_array($auth->group_id, Group::$limitedGroups);
        $user['is_limited'] = $auth->group_id == Group::GROUP_ID_LIMITED;
        $user['is_limited_debt'] = $auth->group_id == Group::GROUP_ID_LIMITED_DEBT;
        $user['is_required_bank_account'] = $auth->group_id == Group::GROUP_ID_LIMITED_BANK_ACCOUNT;
        $user['is_worker'] = !$user['is_admin_group'] && $auth->view_mode == User::MODE_CONTRACTOR;
        $user['is_client'] = !$user['is_admin_group'] && $auth->view_mode == User::MODE_OUTSOURCER;
        $user['is_projectable'] = $auth->projectable;
        $user['is_company'] = $auth->company;
        $user['is_sns'] = $auth->social->exists;

        // Set other infomartion

        return $user;
    }
}
