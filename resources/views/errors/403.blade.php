@extends('errors.layout')
@section('code', '403')
@section('title', 'This action is unauthorized')
@section('message', $exception?->getMessage() && ! str_contains($exception->getMessage(), 'User does not have') ? $exception->getMessage() : 'Your role does not have permission to open this page. Ask an administrator to grant it under Roles.')
