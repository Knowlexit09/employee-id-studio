@extends('studio.layout')
@section('title', ($employee ? 'Edit' : 'Create').' employee · ID Studio')
@section('content')
<a class="text-sm text-indigo-700" href="{{ route('studio.employees.index') }}">← Employees</a>
<h1 class="text-2xl font-bold my-4">{{ $employee ? 'Edit employee' : 'Create employee' }}</h1>

<form method="POST" enctype="multipart/form-data"
      action="{{ $employee ? route('studio.employees.update', $employee) : route('studio.employees.store') }}"
      class="space-y-6 max-w-5xl">
  @csrf
  @if($employee)
    @method('PUT')
  @endif

  @php
    $personalFields = [
        ['employee_code','Employee number','text'],
        ['first_name','First name','text'],
        ['middle_name','Middle name','text'],
        ['last_name','Last name','text'],
        ['suffix','Suffix','text'],
        ['job_title','Job title','text'],
        ['department','Department','text'],
        ['branch','Branch','text'],
        ['email','Email','email'],
        ['phone','Phone','tel'],
        ['hire_date','Hire date','date'],
    ];
    $emergencyFields = [
        'emergency_name'=>'Contact name',
        'emergency_relationship'=>'Relationship',
        'emergency_phone'=>'Phone number',
    ];
  @endphp

  <section class="bg-white border rounded-xl p-6">
    <h2 class="text-lg font-bold mb-4">Personal & employment information</h2>
    <div class="grid gap-4 md:grid-cols-3">
      @foreach($personalFields as $field)
        @php
          [$key, $label, $type] = $field;
          $value = $employee ? $employee->{$key} : null;
          if ($value instanceof \Carbon\CarbonInterface) {
              $value = $value->format('Y-m-d');
          }
          $requiredField = in_array($key, ['employee_code','first_name','last_name'], true);
        @endphp
        <label class="text-sm font-medium">
          {{ $label }}
          <input class="mt-1 block w-full rounded-lg border p-2.5"
                 type="{{ $type }}" name="{{ $key }}" value="{{ old($key, $value) }}"
                 {{ $requiredField ? 'required' : '' }}>
        </label>
      @endforeach
      <label class="text-sm font-medium">Status
        <select name="status" class="mt-1 block w-full rounded-lg border p-2.5">
          <option value="active" @selected(old('status',$employee?->status ?? 'active') === 'active')>Active</option>
          <option value="inactive" @selected(old('status',$employee?->status ?? 'active') === 'inactive')>Inactive</option>
        </select>
      </label>
      <label class="text-sm font-medium">Employee photo (JPEG/PNG/WebP)
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
               class="mt-1 block w-full rounded-lg border p-2.5">
        <span class="text-xs text-slate-500">Max 2 MB and 2048×2048 pixels. Sanitized to JPEG.</span>
      </label>
      @if($employee && $employee->photo_path)
        <img class="h-24 w-24 object-cover rounded-lg border"
             src="{{ route('studio.employees.photo', $employee) }}" alt="Current employee photo">
      @endif
    </div>
  </section>

  <section class="bg-white border rounded-xl p-6">
    <h2 class="text-lg font-bold">Philippine government numbers</h2>
    <p class="text-sm text-slate-500 mb-4">
      Encrypted at rest. Saved numbers are not displayed in the browser.
      Leave a field blank to keep the saved number; tick Clear to delete it.
      These values are excluded from printed ID templates.
    </p>
    <div class="grid gap-4 md:grid-cols-2">
      <div>
        <label class="text-sm font-medium block" for="private-sss_number">SSS number</label>
        <input id="private-sss_number" name="sss_number" type="text" value="" maxlength="32"
          class="block mt-1 rounded-lg border p-2.5 w-full" autocomplete="off" spellcheck="false"
          placeholder="{{ $employee && filled($employee->sss_number) ? 'Saved securely — leave blank to keep' : 'Enter if available' }}">
        <p class="text-xs text-slate-500 mt-1">{{ $employee && filled($employee->sss_number) ? 'Saved securely' : 'No saved number' }}</p>
        @if($employee)
          <label class="mt-2 inline-flex items-center gap-2 text-sm text-red-700">
            <input type="checkbox" name="clear_sss_number" value="1">
            Clear this number
          </label>
        @endif
      </div>
      <div>
        <label class="text-sm font-medium block" for="private-philhealth_number">PhilHealth PIN</label>
        <input id="private-philhealth_number" name="philhealth_number" type="text" value="" maxlength="32"
          class="block mt-1 rounded-lg border p-2.5 w-full" autocomplete="off" spellcheck="false"
          placeholder="{{ $employee && filled($employee->philhealth_number) ? 'Saved securely — leave blank to keep' : 'Enter if available' }}">
        <p class="text-xs text-slate-500 mt-1">{{ $employee && filled($employee->philhealth_number) ? 'Saved securely' : 'No saved number' }}</p>
        @if($employee)
          <label class="mt-2 inline-flex items-center gap-2 text-sm text-red-700">
            <input type="checkbox" name="clear_philhealth_number" value="1">
            Clear this number
          </label>
        @endif
      </div>
      <div>
        <label class="text-sm font-medium block" for="private-pagibig_mid">Pag-IBIG MID</label>
        <input id="private-pagibig_mid" name="pagibig_mid" type="text" value="" maxlength="32"
          class="block mt-1 rounded-lg border p-2.5 w-full" autocomplete="off" spellcheck="false"
          placeholder="{{ $employee && filled($employee->pagibig_mid) ? 'Saved securely — leave blank to keep' : 'Enter if available' }}">
        <p class="text-xs text-slate-500 mt-1">{{ $employee && filled($employee->pagibig_mid) ? 'Saved securely' : 'No saved number' }}</p>
        @if($employee)
          <label class="mt-2 inline-flex items-center gap-2 text-sm text-red-700">
            <input type="checkbox" name="clear_pagibig_mid" value="1">
            Clear this number
          </label>
        @endif
      </div>
      <div>
        <label class="text-sm font-medium block" for="private-tin">BIR TIN</label>
        <input id="private-tin" name="tin" type="text" value="" maxlength="32"
          class="block mt-1 rounded-lg border p-2.5 w-full" autocomplete="off" spellcheck="false"
          placeholder="{{ $employee && filled($employee->tin) ? 'Saved securely — leave blank to keep' : 'Enter if available' }}">
        <p class="text-xs text-slate-500 mt-1">{{ $employee && filled($employee->tin) ? 'Saved securely' : 'No saved number' }}</p>
        @if($employee)
          <label class="mt-2 inline-flex items-center gap-2 text-sm text-red-700">
            <input type="checkbox" name="clear_tin" value="1">
            Clear this number
          </label>
        @endif
      </div>
    </div>
  </section>

  <section class="bg-white border rounded-xl p-6">
    <h2 class="text-lg font-bold mb-4">Emergency contact</h2>
    <div class="grid gap-4 md:grid-cols-3">
      @foreach($emergencyFields as $key => $label)
        <label class="text-sm font-medium">
          {{ $label }}
          <input class="block mt-1 rounded-lg border p-2.5 w-full"
                 name="{{ $key }}" value="{{ old($key, $employee ? $employee->{$key} : null) }}">
        </label>
      @endforeach
    </div>
  </section>

  <div class="flex flex-wrap gap-4">
    <button type="submit" class="rounded-lg bg-indigo-700 text-white px-6 py-3 font-semibold">Save employee</button>
    @if($employee)
      <a class="rounded-lg bg-white border px-6 py-3 font-semibold"
         href="{{ route('studio.cards.form', $employee) }}">Issue / Revoke ID</a>
    @endif
  </div>
</form>
@endsection
