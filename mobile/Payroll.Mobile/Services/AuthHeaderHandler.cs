using System.Net.Http.Headers;
using Payroll.Mobile.Services;

namespace Payroll.Mobile.Services;

public sealed class AuthHeaderHandler : DelegatingHandler
{
    private readonly TokenStorageService _tokens;

    public AuthHeaderHandler(TokenStorageService tokens)
    {
        _tokens = tokens;
    }

    protected override async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
    {
        var token = await _tokens.GetTokenAsync();
        if (!string.IsNullOrWhiteSpace(token))
        {
            request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);
        }

        return await base.SendAsync(request, cancellationToken);
    }
}
