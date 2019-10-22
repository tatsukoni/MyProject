@extends('emails.layouts.admin')

@section('contents')

シュフティ事業部様

@if ($attachCsv)
処理に成功しました。
添付のCSVファイルから、結果をご確認ください。
@else
下記の理由により、処理に失敗しました。

【エラーメッセージ】
{{ $resultMessage }}
@endif

@endsection