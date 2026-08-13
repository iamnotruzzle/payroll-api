namespace Payroll.Mobile.Services;

public sealed class ApiBaseUrlHandler : DelegatingHandler
{
    private readonly IApiBaseUrlProvider _urls;

    public ApiBaseUrlHandler(IApiBaseUrlProvider urls)
    {
        _urls = urls;
    }

    protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
    {
        if (request.RequestUri is not null)
        {
            var baseUri = new Uri(ApiBaseUrlProvider.Normalize(_urls.Current) + "/");
            var relative = request.RequestUri.IsAbsoluteUri
                ? request.RequestUri.PathAndQuery
                : request.RequestUri.OriginalString;
            request.RequestUri = new Uri(baseUri, relative.TrimStart('/'));
        }

        return base.SendAsync(request, cancellationToken);
    }
}
