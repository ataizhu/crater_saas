@component('mail::message')
# Welcome to Crater!

Your Crater account has been created successfully.

**Company Name:** {{ $tenant->name }}

**Login Details:**
- URL: {{ $loginUrl }}
- Email: {{ $tenant->owner_email }}
- Password: (the password you set during registration)

@component('mail::button', ['url' => $loginUrl])
Login to Your Account
@endcomponent

You can now start managing your invoices, estimates, payments and expenses.

Thanks,<br>
{{ config('app.name') }}
@endcomponent

