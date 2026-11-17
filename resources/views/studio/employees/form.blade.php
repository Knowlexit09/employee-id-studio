@extends('studio.layout')
@section('title',($employee ? 'Edit' : 'Create').' employee · ID Studio')
@section('content')
<a class="text-sm text-indigo-700" href="{{ route('studio.employees.index') }}">← Employees</a><h1 class="text-2xl font-bold my-4">{{ $employee ? 'Edit employee' : 'Create employee' }}</h1>
<form method="POST" enctype="multipart/form-data" action="{{ $employee ? route('studio.employees.update',$employee) : route('studio.employees.store') }}" class="space-y-6 max-w-5xl">@csrf @if($employee)@method('PUT')@endif
@php($fields=[['employee_code','Employee number','text'],['first_name','First name','text'],['middle_name','Middle name','text'],['last_name','Last name','text'],['suffix','Suffix','text'],['job_title','Job title','text'],['department','Department','text'],['branch','Branch','text'],['email','Email','email'],['phone','Phone','tel'],['hire_date','Hire date','date']])
<section class="bg-white border rounded-xl p-6"><h2 class="text-lg font-bold mb-4">Personal & employment information</h2><div class="grid gap-4 md:grid-cols-3">@foreach($fields as [$key,$label,$type])<label class="text-sm font-medium">{{ $label }}<input class="mt-1 block w-full rounded-lg border p-2.5" type="{{ $type }}" name="{{ $key }}" value="{{ old($key, $employee?->{$key} instanceof \Carbon\CarbonInterface ? $employee->{$key}->format('Y-m-d') : $employee?->{$key}) }}" @if(in_array($key,['employee_code','first_name','last_name']))required@endif></label>@endforeach
<label class="text-sm font-medium">Status<select name="status" class="mt-1 block w-full rounded-lg border p-2.5"><option value="active" @selected(old('status',$employee?->status ?? 'active')==='active')>Active</option><option value="inactive" @selected(old('status',$employee?->status)==='inactive')>Inactive</option></select></label>
<label class="text-sm font-medium">Employee photo (JPEG/PNG/WebP)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-lg border p-2.5"></label>
@if($employee?->photo_path)<img class="h-24 w-24 object-cover rounded-lg border" src="{{ route('studio.employees.photo',$employee) }}" alt="Current employee photo">@endif</div></section>
<section class="bg-white border rounded-xl p-6">
  <h2 class="text-lg font-bold">Philippine government numbers</h2>
  <p class="text-sm text-slate-500 mb-4">Numbers are encrypted, never displayed in the edit form. Blank means keep the saved value. Tick Clear to remove one.</p>
  <div class="grid gap-4 md:grid-cols-2">
    <div>
      <label class="block text-sm font-medium" for="sss_number">SSS number</label>
      <input id="sss_number" class="block mt-1 rounded-lg border p-2.5 w-full" name="sss_number" type="text"
        value="" autocomplete="off" spellcheck="false" maxlength="32"
        placeholder="{{ $employee && filled($employee->sss_number) ? 'Saved securely — leave blank to keep' : 'Enter if available' }}">
      <p class="text-xs text-slate-500 mt-1">{{ $employee && filled($employee->sss_number) ? 'Saved securely' : 'No saved number' }}</p>
      @if($employee)
      <label class="flex items-center gap-2 mt-2 text-sm text-red-700">
        <input type="checkbox" name="clear_sss_number" value="1"> Clear this number
      </label>
      @endif
    </div>
    <div>
      <label class="block text-sm font-medium" for="philhealth_number">PhilHealth PIN</label>
      <input id="philhealth_number" class="block mt-1 rounded-lg border p-2.5 w-full" name="philhealth_number" type="text"
        value="" autocomplete="off" spellcheck="false" maxlength="32"
        placeholder="{{ $employee && filled($employee->philhealth_number) ? 'Saved securely — leave blank to keep' : 'Enter if available' }}">
      <p class="text-xs text-slate-500 mt-1">{{ $employee && filled($employee->philhealth_number) ? 'Saved securely' : 'No saved number' }}</p>
      @if($employee)
      <label class="flex items-center gap-2 mt-2 text-sm text-red-700">
        <input type="checkbox" name="clear_philhealth_number" value="1"> Clear this number
      </label>
      @endif
    </div>
    <div>
      <label class="block text-sm font-medium" for="pagibig_mid">Pag-IBIG MID</label>
      <input id="pagibig_mid" class="block mt-1 rounded-lg border p-2.5 w-full" name="pagibig_mid" type="text"
        value="" autocomplete="off" spellcheck="false" maxlength="32"
        placeholder="{{ $employee && filled($employee->pagibig_mid) ? 'Saved securely — leave blank to keep' : 'Enter if available' }}">
      <p class="text-xs text-slate-500 mt-1">{{ $employee && filled($employee->pagibig_mid) ? 'Saved securely' : 'No saved number' }}</p>
      @if($employee)
      <label class="flex items-center gap-2 mt-2 text-sm text-red-700">
        <input type="checkbox" name="clear_pagibig_mid" value="1"> Clear this number
      </label>
      @endif
    </div>
    <div>
      <label class="block text-sm font-medium" for="tin">BIR TIN</label>
      <input id="tin" class="block mt-1 rounded-lg border p-2.5 w-full" name="tin" type="text"
        value="" autocomplete="off" spellcheck="false" maxlength="32"
        placeholder="{{ $employee && filled($employee->tin) ? 'Saved securely — leave blank to keep' : 'Enter if available' }}">
      <p class="text-xs text-slate-500 mt-1">{{ $employee && filled($employee->tin) ? 'Saved securely' : 'No saved number' }}</p>
      @if($employee)
      <label class="flex items-center gap-2 mt-2 text-sm text-red-700">
        <input type="checkbox" name="clear_tin" value="1"> Clear this number
      </label>
      @endif
    </div>
  </div>
</section>
<section class="bg-white border rounded-xl p-6"><h2 class="text-lg font-bold mb-4">Emergency contact</h2><div class="grid gap-4 md:grid-cols-3">@foreach(['emergency_name'=>'Contact name','emergency_relationship'=>'Relationship','emergency_phone'=>'Phone number'] as $key=>$label)<label class="text-sm font-medium">{{ $label }}<input class="block mt-1 rounded-lg border p-2.5 w-full" name="{{ $key }}" value="{{ old($key,$employee?->{$key}) }}"></label>@endforeach</div></section>
<div class="flex gap-4"><button class="rounded-lg bg-indigo-700 text-white px-6 py-3 font-semibold">Save employee</button>@if($employee)<a class="rounded-lg bg-white border px-6 py-3 font-semibold" href="{{ route('studio.cards.form',$employee) }}">Issue / Revoke ID</a>@endif</div>
</form>
@endsection
