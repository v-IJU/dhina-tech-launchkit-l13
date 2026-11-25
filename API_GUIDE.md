# API Guide for App Developers

How a mobile app (React Native, Flutter, iOS/Android) or another frontend talks to this project.

| Resource                     | Where                                                                           |
| ---------------------------- | ------------------------------------------------------------------------------- |
| **Interactive docs** (try every endpoint in the browser) | `http://localhost:8080/docs/api`                     |
| **OpenAPI file** (import into Postman / Insomnia, or generate TypeScript types) | `http://localhost:8080/docs/api.json` |
| Base URL                     | `http://localhost:8080/api/v1` (production: `https://your-domain.com/api/v1`)   |

> The docs pages are open to anyone on a local machine. On a server, sign in to the admin panel first as a super admin or a user with `settings.view`.

---

## 1. Who uses this API

The `/api/v1` API is for **staff accounts**: the same users who sign in to the admin panel (super admin, admin, editor, or any role you create). A token can do **exactly what that user's role allows** in the admin panel, nothing more.

Typical apps:
- an **editor app** for writing blog posts, uploading photos and answering FAQs from a phone
- an **admin / field app** for staff
- a **React / Next.js admin frontend** in place of the Blade panel

**E-commerce customers, patients, students and other end users are not staff.** They have their own accounts (`customers` table) and their own API under **`/api/v1/shop`**. See [section 9](#9-customer-app-api-apiv1shop).

---

## 2. Every request

```
Accept: application/json
Authorization: Bearer <token>              ← every endpoint except POST /auth/token
Content-Type: application/json             ← when sending JSON
```

---

## 3. Sign-in flow

### 3.1 Get a token

```http
POST /api/v1/auth/token
{ "email": "editor@example.com", "password": "Secret#123", "device_name": "Anita's iPhone" }
```

```json
201 Created
{
  "token": "7|pQx3…",
  "token_type": "Bearer",
  "expires_at": null,
  "user": {
    "id": 5, "name": "Anita Raj", "email": "editor@example.com", "username": "anita",
    "roles": ["editor"],
    "permissions": ["dashboard.view", "blog.view", "blog.create", "blog.edit", "blog.publish", "media.view", "media.create", "faqs.view", "…"],
    "two_factor": false
  }
}
```

| Response | Meaning                                                                                                  | What the app does                          |
| -------- | -------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| `201`    | Signed in                                                                                                | Save the token in **secure storage**        |
| `422` `errors.email` | Wrong email/password, account not active, or 2FA must be set up in the admin panel first     | Show the message                           |
| `422` `errors.code`  | The account uses **two-factor login**                                                        | Show a 6-digit code field, send again with `"code": "123456"` |
| `429`    | 5 attempts per minute reached                                                                            | "Try again in a minute"                    |

### 3.2 Two-factor accounts

Send the same request again with the code from the user's authenticator app, or one of their recovery codes:

```json
{ "email": "…", "password": "…", "device_name": "…", "code": "482913" }
```

### 3.3 Staying signed in

- Tokens don't expire unless **Settings → Security → "App sign-in tokens expire after (days)"** is set.
- Any `401` response means the token is gone: it was revoked, expired, or the user was deactivated or deleted. **Delete the stored token and show the login screen.**
- `GET /api/v1/me` on app start checks that the token still works and refreshes the user's permissions.

### 3.4 Sign out

```http
DELETE /api/v1/auth/token
```

This revokes only the token used for the request; other devices stay signed in. Admins and users can also revoke tokens on **Profile → Security → API tokens**.

---

## 4. Permissions: what each user can do

`GET /api/v1/me` returns `permissions`. **Use it to show or hide features in the app.** The server checks every request again anyway, and returns `403` when it's not allowed.

```ts
const can = (perm: string) => me.permissions.includes('*') || me.permissions.includes(perm);

{can('blog.create') && <Button title="New post" onPress={…} />}
{can('blog.delete') && <Button title="Delete" onPress={…} />}
```

Permission names follow `resource.action`: `blog.view`, `blog.create`, `blog.edit`, `blog.delete`, `blog.publish`…

**What the default roles get through the API:**

| Feature (endpoints)                              | Super admin | Admin | Editor |
| ------------------------------------------------ | :---------: | :---: | :----: |
| Read blog posts and categories                   | ✅ | ✅ | ✅ |
| Create / edit blog posts                         | ✅ | ✅ | ✅ |
| Publish posts (`status: published`)              | ✅ | ✅ | ✅ |
| Delete posts                                     | ✅ | ✅ | ❌ |
| Upload / list media (photos)                     | ✅ | ✅ | ✅ |
| FAQs, Services, Sliders, Testimonials: read/create/edit | ✅ | ✅ | ✅ |
| FAQs, Services, Sliders, Testimonials: delete    | ✅ | ✅ | ❌ |

Roles and their permissions are changed in the admin panel (**Access control → Roles**), and the API follows straight away. A user without `blog.publish` can still send `"status": "published"`, but the post is saved as a draft.

**Read-only tokens** (made on Profile → Security) can only call `GET`. Anything else returns `403 "This token is read-only."`

---

## 5. Endpoints

| Method | URL                               | Permission       | What it does                                   |
| ------ | --------------------------------- | ---------------- | ---------------------------------------------- |
| POST   | `/auth/token`                     | none             | Sign in, get a token                           |
| GET    | `/me`                             | any              | Current user, roles, permissions               |
| DELETE | `/auth/token`                     | any              | Sign out (revoke this token)                   |
| GET    | `/blog/posts`                     | `blog.view`      | List posts (search, filters, pages)            |
| POST   | `/blog/posts`                     | `blog.create`    | Create a post                                  |
| GET    | `/blog/posts/{id}`                | `blog.view`      | One post, including `content`                  |
| PUT    | `/blog/posts/{id}`                | `blog.edit`      | Update a post (send every required field)      |
| DELETE | `/blog/posts/{id}`                | `blog.delete`    | Move to trash                                  |
| GET    | `/blog/categories`                | `blog.view`      | Categories for the post form                   |
| GET    | `/media`                          | `media.view`     | Latest uploaded files                          |
| POST   | `/media`                          | `media.create`   | Upload a file (multipart, field `file`)        |
| GET/POST/GET/PUT/DELETE | `/faqs`, `/faqs/{id}` | `faqs.*`      | FAQs                                           |
| same                    | `/services`, `/services/{id}` | `services.*` | Services                                |
| same                    | `/sliders`, `/sliders/{id}` | `sliders.*` | Home page slides                          |
| same                    | `/testimonials`, `/testimonials/{id}` | `testimonials.*` | Testimonials                    |

**More endpoints arrive automatically.** Every module generated with `php artisan make:module X --api` adds `/x` with the same five actions, and it appears in `/docs/api` without any extra work.

### 5.1 Lists: query parameters

| Parameter     | Example                 | Notes                                           |
| ------------- | ----------------------- | ----------------------------------------------- |
| `q`           | `q=docker`              | Search                                          |
| `sort`, `dir` | `sort=title&dir=asc`    | Allowed sort columns are listed in `/docs/api`  |
| `per_page`    | `per_page=25`           | 10, 15, 25, 50 or 100                           |
| `page`        | `page=2`                |                                                 |
| `status`      | `status=published`      | Posts: draft / published / scheduled. Others: `1` active, `0` inactive |
| `from`, `to`  | `from=2026-01-01`       | Date range (`YYYY-MM-DD`)                       |
| `category`, `featured`, `lang` | `category=2&lang=ta` | Blog posts only                    |

```json
{
  "data": [ { "id": 12, "title": "…", "slug": "…", "url": "https://…/blog/…", "status": "published",
              "category": { "id": 2, "name": "News", "slug": "news" }, "author": { "id": 5, "name": "Anita Raj" },
              "featured_image": "https://…/storage/1/cover.jpg", "tags": ["app"], "published_at": "2026-11-18T10:00:00+05:30" } ],
  "links": { "first": "…?page=1", "last": "…?page=4", "prev": null, "next": "…?page=2" },
  "meta":  { "current_page": 1, "last_page": 4, "per_page": 15, "total": 52 }
}
```

For infinite scroll, keep requesting the next page while `meta.current_page < meta.last_page`.

### 5.2 Errors

| Status | Body                                                                    | App action                         |
| ------ | ----------------------------------------------------------------------- | ---------------------------------- |
| `401`  | `{"message":"Unauthenticated."}`                                        | Clear the token, go to login       |
| `403`  | `{"message":"You do not have permission to do this."}`                  | Hide the feature / show message    |
| `404`  | `{"message":"…not found"}`                                              | Item was deleted                   |
| `422`  | `{"message":"…","errors":{"title":["The title field is required."]}}`  | Show each error under its field    |
| `429`  | `{"message":"Too Many Attempts."}`                                      | Wait (default 60 requests/minute)  |
| `503`  | `{"message":"The API is turned off."}`                                  | Show "maintenance"                 |

---

## 6. React Native example: an editor app

### 6.1 API client (axios + secure storage)

```bash
npx expo install expo-secure-store expo-image-picker expo-device
npm install axios
```

```ts
// src/api.ts
import axios from 'axios';
import * as SecureStore from 'expo-secure-store';

export const api = axios.create({
  baseURL: 'https://your-domain.com/api/v1',     // Android emulator on local Docker: http://10.0.2.2:8080/api/v1
  headers: { Accept: 'application/json' },
  timeout: 20000,
});

api.interceptors.request.use(async (config) => {
  const token = await SecureStore.getItemAsync('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

let onSignedOut: () => void = () => {};
export const setSignedOutHandler = (fn: () => void) => { onSignedOut = fn; };

api.interceptors.response.use(
  (res) => res,
  async (error) => {
    if (error.response?.status === 401) {          // token revoked / expired / user deactivated
      await SecureStore.deleteItemAsync('token');
      onSignedOut();
    }
    return Promise.reject(error);
  },
);

// 422 → { field: "first message" } for forms
export const fieldErrors = (error: any): Record<string, string> =>
  Object.fromEntries(Object.entries(error.response?.data?.errors ?? {}).map(([k, v]: any) => [k, v[0]]));
```

### 6.2 Login (with 2FA)

```ts
// src/auth.ts
import * as SecureStore from 'expo-secure-store';
import * as Device from 'expo-device';
import { api } from './api';

export async function login(email: string, password: string, code?: string) {
  const { data } = await api.post('/auth/token', {
    email, password, code,
    device_name: Device.deviceName ?? 'Mobile app',
  });
  await SecureStore.setItemAsync('token', data.token);
  return data.user;                                   // { roles, permissions, … }
}

export async function me() {
  return (await api.get('/me')).data.data;
}

export async function logout() {
  try { await api.delete('/auth/token'); } finally { await SecureStore.deleteItemAsync('token'); }
}
```

```tsx
// LoginScreen.tsx (essentials)
const [needsCode, setNeedsCode] = useState(false);

async function submit() {
  try {
    const user = await login(email, password, needsCode ? code : undefined);
    signIn(user);
  } catch (e: any) {
    const errors = e.response?.data?.errors ?? {};
    if (errors.code) setNeedsCode(true);              // ask for the 6-digit code
    setError(errors.email?.[0] ?? errors.code?.[0] ?? 'Could not sign in');
  }
}
```

### 6.3 List posts with infinite scroll

```tsx
import { useEffect, useState } from 'react';
import { FlatList, Text, TextInput } from 'react-native';
import { api } from './api';

export function PostsScreen() {
  const [posts, setPosts] = useState<any[]>([]);
  const [meta, setMeta] = useState({ current_page: 0, last_page: 1 });
  const [q, setQ] = useState('');

  async function load(page = 1) {
    const { data } = await api.get('/blog/posts', { params: { page, per_page: 25, q, sort: 'updated_at', dir: 'desc' } });
    setPosts(page === 1 ? data.data : [...posts, ...data.data]);
    setMeta(data.meta);
  }

  useEffect(() => { load(1); }, [q]);

  return (
    <>
      <TextInput placeholder="Search posts" value={q} onChangeText={setQ} />
      <FlatList
        data={posts}
        keyExtractor={(p) => String(p.id)}
        renderItem={({ item }) => <Text>{item.title} · {item.status}</Text>}
        onEndReached={() => meta.current_page < meta.last_page && load(meta.current_page + 1)}
      />
    </>
  );
}
```

### 6.4 Create a post with a photo

1. Pick a photo and upload it to `/media`.
2. Send the returned `url` as `featured_image` when creating the post.
3. `author_id` can be left out: the signed-in user becomes the author.

```ts
import * as ImagePicker from 'expo-image-picker';
import { api, fieldErrors } from './api';

export async function uploadPhoto(): Promise<string | null> {
  const picked = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.8 });
  if (picked.canceled) return null;
  const asset = picked.assets[0];

  const form = new FormData();
  form.append('file', { uri: asset.uri, name: asset.fileName ?? 'photo.jpg', type: asset.mimeType ?? 'image/jpeg' } as any);

  const { data } = await api.post('/media', form, { headers: { 'Content-Type': 'multipart/form-data' } });
  return data.data.url;
}

export async function createPost(form: { title: string; content: string; category_id: number; publish: boolean }) {
  const featured_image = await uploadPhoto();
  try {
    const { data } = await api.post('/blog/posts', {
      title: form.title,
      content: `<p>${form.content.replace(/\n/g, '</p><p>')}</p>`,   // content is HTML
      category_id: form.category_id,                                  // from GET /blog/categories
      status: form.publish ? 'published' : 'draft',                   // without blog.publish it stays a draft
      featured_image,
      tags: 'mobile,app',                                             // comma separated
    });
    return data.data;                                                 // the saved post
  } catch (e) {
    throw fieldErrors(e);                                             // { title: "…", category_id: "…" }
  }
}
```

**Post fields:**

| Field              | Required             | Notes                                                    |
| ------------------ | -------------------- | -------------------------------------------------------- |
| `title`            | yes                  | max 200                                                  |
| `content`          | yes                  | HTML. Unsafe tags (`<script>`…) are removed               |
| `category_id`      | yes                  | from `/blog/categories`                                  |
| `status`           | yes                  | `draft`, `published`, `scheduled`                        |
| `published_at`     | when `scheduled`     | `2026-12-01 09:00:00`                                    |
| `slug`             | no                   | made from the title                                      |
| `excerpt`          | no                   | max 500                                                  |
| `featured_image`   | no                   | a URL, e.g. from `POST /media`                           |
| `tags`             | no                   | `"app,mobile"`                                           |
| `is_featured`      | no                   | `true` / `false`                                         |
| `author_id`        | no                   | defaults to the signed-in user                           |
| `meta_title`, `meta_description` | no     | SEO, max 70 / 170                                        |

**Editing:** `PUT /blog/posts/{id}` with **all required fields** (title, content, category_id, status). Load the post with `GET /blog/posts/{id}`, change it, and send it back.

---

## 7. Testing without the app

- **Browser:** open `/docs/api`, pick an endpoint, enter your token in its **Auth** box (Bearer), and send the request.
- **Postman:** Import → Link → `http://localhost:8080/docs/api.json`. Set the collection's Auth to *Bearer Token*.
- **curl:**
  ```bash
  TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/token -H "Content-Type: application/json" \
    -d '{"email":"admin@example.com","password":"Admin@12345","device_name":"curl"}' | sed 's/.*"token":"\([^"]*\)".*/\1/')
  curl http://localhost:8080/api/v1/blog/posts -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
  ```
- **TypeScript types** for the app from the same file:
  ```bash
  npx openapi-typescript http://localhost:8080/docs/api.json -o src/api-types.ts
  ```

On a server, `/docs/api.json` needs an admin session. Download it once and commit it to the app repository, or run the generator against your local Docker copy.

---

## 8. Backend developers: adding endpoints

| Want                                    | Do                                                                                  |
| --------------------------------------- | ----------------------------------------------------------------------------------- |
| API for a new module                    | `php artisan make:module Product --fields="…" --api --migrate`                      |
| Change the JSON of a record             | `app/Modules/X/Resources/XResource.php`                                             |
| Extra endpoint                          | Add a method to `Controllers/Api/XController.php` + a route in `Routes/api.php`     |
| Describe it in `/docs/api`              | PHPDoc summary on the method, `#[QueryParameter(...)]` for query params; request bodies come from the FormRequest automatically |
| Protect it                              | `new Middleware('permission:x.something', only: ['method'])`                         |

The docs are generated from the code by **Scramble** (`dedoc/scramble`, config in `config/scramble.php`), so they can't go out of date.

---

## 9. Customer app API (`/api/v1/shop`)

For your **customers' app**: shoppers, patients, students, members. These accounts live in the `customers` table (Customers module), never in `users`, so a customer can never reach the admin panel or the staff API.

| | Staff app | Customer app |
| --- | --- | --- |
| Accounts | `users` (admin panel) | `customers` (website "Sign in" + app) |
| Base URL | `/api/v1` | `/api/v1/shop` |
| Sign in | `POST /api/v1/auth/token` | `POST /api/v1/shop/auth/token` |
| Sign up | No (admins add staff) | `POST /api/v1/shop/auth/register` |
| Access | Role permissions | Own data only |
| Docs groups | Faqs, Posts, Media… | "Shop · …" in `/docs/api` |

A staff token on `/shop` and a customer token on staff endpoints both get **403**.

### 9.1 Endpoints

| Method | Path (after `/api/v1/shop`) | Token | What it does |
| --- | --- | --- | --- |
| POST | `/auth/register` | – | Create account → `{token, customer}` (201) |
| POST | `/auth/token` | – | Sign in → `{token, customer}`; 2FA accounts also send `code` |
| POST | `/auth/forgot-password` | – | Email a reset link (same answer for unknown emails) |
| POST | `/auth/reset-password` | – | `token`, `email`, `password`, `password_confirmation` |
| DELETE | `/auth/token` | ✓ | Sign out this device |
| GET | `/me` | ✓ | Profile (`email_verified`, `needs_verification`, `two_factor`, `default_address`) |
| PUT | `/me` | ✓ | Update `name`, `phone`, `date_of_birth`, `gender`, `locale`, `marketing_opt_in`; `email` needs `current_password` |
| PUT | `/me/password` | ✓ | `current_password`, `password`, `password_confirmation`. Other devices are signed out |
| DELETE | `/me` | ✓ | Delete account (`password`). **Required by App Store / Google Play** for apps with sign up |
| POST | `/email/verification-notification` | ✓ | Resend the confirmation email |
| POST | `/two-factor` | ✓ | Start 2FA setup (`password`) → `secret`, `otpauth_url`, `qr_svg` |
| POST | `/two-factor/confirm` | ✓ | `code` → 2FA on + `recovery_codes` |
| POST | `/two-factor/recovery-codes` | ✓ | `password` → new codes |
| DELETE | `/two-factor` | ✓ | `password` → 2FA off |
| GET / POST | `/addresses` | ✓ ✉ | List / add (first one becomes the default) |
| GET / PUT / DELETE | `/addresses/{id}` | ✓ ✉ | Show / update / delete |
| POST | `/addresses/{id}/default` | ✓ ✉ | Make default |

✉ = needs a confirmed email when **Settings → Customers → Customers must confirm their email** is on. Otherwise **403** with `"email_verified": false`: show a "Check your inbox" screen with a resend button.

Public endpoints allow 5 requests per minute per email + IP; signed-in ones use the normal API limit.

### 9.2 Sign-in flow (React Native)

```js
// api/shop.js — same axios client as section 6.1, baseURL 'https://your-domain.com/api/v1/shop'
export async function signIn(email, password, code) {
  try {
    const { data } = await shop.post('/auth/token', { email, password, code, device_name: Device.deviceName ?? 'Phone' });
    await SecureStore.setItemAsync('shop_token', data.token);
    return { customer: data.customer };
  } catch (e) {
    const body = e.response?.data;
    if (e.response?.status === 422 && body?.two_factor) {
      return { needsCode: true };           // show a 6-digit field, call signIn(email, password, code)
    }
    throw new Error(body?.message ?? 'Could not sign in');
  }
}

export async function register(form) {
  const { data } = await shop.post('/auth/register', { ...form, device_name: Device.deviceName ?? 'Phone' });
  await SecureStore.setItemAsync('shop_token', data.token);
  return data.customer;                     // if customer.needs_verification → "Check your inbox" screen
}
```

| Answer | Meaning | App should |
| --- | --- | --- |
| 201 + `token` | Signed in | Save the token, go home |
| 422 + `two_factor: true` | 2FA is on | Ask for the code, call again with `code` |
| 422 `errors.email` | Wrong email/password, or blocked | Show the message |
| 401 | Token expired or revoked | Delete the token, show sign in |
| 403 + `email_verified: false` | Email not confirmed | "Check your inbox" + resend |
| 403 "This account has been blocked." | Staff blocked the customer | Sign out, show the message |
| 503 | Customer accounts or the API are switched off | "Not available right now" |

**Password reset links** open the website page `/account/reset-password/{token}?email=…`. To handle them inside the app instead, set up a deep link for that URL and call `POST /auth/reset-password` with the token and email from it.

### 9.3 Backend: adding customer endpoints (orders, wishlist…)

Any module can add customer endpoints with two route files. The **file name decides who can call it**:

| File | URL | Middleware group |
| --- | --- | --- |
| `app/Modules/Orders/Routes/shop.php` | `/api/v1/shop/...` | `api.customer`: customer token required |
| `app/Modules/Orders/Routes/shop_guest.php` | `/api/v1/shop/...` | `api.customer.guest`: public, e.g. product catalogue |

```php
// app/Modules/Orders/Routes/shop.php
Route::get('orders', [OrderController::class, 'index'])->name('orders.index');          // route('api.shop.orders.index')
Route::post('orders', [OrderController::class, 'store'])->middleware('customer.verified');

// OrderController: always scope to the signed-in customer
public function index(Request $request) {
    return OrderResource::collection($request->user()->orders()->latest()->paginate());
}
```

Put `#[Group('Shop · Orders')]` on the controller so the docs list it with the other shop endpoints. A v2 shop API works like the staff one: add `'v2' => 'Routes/shop_v2.php'` to `shop_api_versions` in `config/starter.php`.
