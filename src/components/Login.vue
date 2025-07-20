<template>
  <div class="login-container">
    <form @submit.prevent="login" class="login-form">
      <div class="login-header">
        <svg class="login-icon" xmlns="http://www.w3.org/2000/svg" height="48" viewBox="0 0 24 24" width="48"><path fill="#1976d2" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        <h2>Login</h2>
      </div>
      <label for="password">Passwort:</label>
      <input type="password" v-model="password" id="password" required placeholder="Passwort eingeben" />
      <button type="submit" class="login-btn">Login</button>
      <div v-if="error" class="error">{{ error }}</div>
    </form>
  </div>
</template>

<script>
export default {
  name: 'Login',
  data() {
    return {
      password: '',
      error: ''
    };
  },
  methods: {
    async login() {
      this.error = '';
      try {
        const res = await fetch('/iobroker/api/login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ password: this.password })
        });
        let data = null;
        try {
          const text = await res.text();
          data = text ? JSON.parse(text) : {};
        } catch (jsonErr) {
          this.error = 'Ungültige Serverantwort';
          return;
        }
        if (res.ok && data.token) {
          localStorage.setItem('auth_token', data.token);
          this.$emit('login-success');
        } else {
          this.error = data.error || 'Login fehlgeschlagen';
        }
      } catch (e) {
        this.error = 'Serverfehler';
      }
    }
  }
};
</script>

<style scoped>
.login-container {
  max-width: 350px;
  margin: 60px auto;
  padding: 32px 24px 24px 24px;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 2px 16px rgba(0,0,0,0.08);
}
.login-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 24px;
}
.login-icon {
  margin-bottom: 8px;
}
.login-form label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
}
.login-form input[type="password"] {
  width: 100%;
  padding: 10px;
  margin-bottom: 16px;
  border: 1px solid #bdbdbd;
  border-radius: 6px;
  font-size: 16px;
}
.login-btn {
  width: 100%;
  padding: 10px;
  background: #1976d2;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.login-btn:hover {
  background: #1565c0;
}
.error {
  color: #d32f2f;
  margin-top: 12px;
  text-align: center;
}
</style>
