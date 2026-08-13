namespace Payroll.Mobile.Services;

public sealed class TokenStorageService
{
    public const string TokenKey = "auth_token";

    public Task SetTokenAsync(string token) => SecureStorage.Default.SetAsync(TokenKey, token);

    public Task<string?> GetTokenAsync() => SecureStorage.Default.GetAsync(TokenKey);

    public void Clear() => SecureStorage.Default.Remove(TokenKey);
}
