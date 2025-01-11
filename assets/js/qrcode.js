


document.addEventListener("DOMContentLoaded", () => {
    const qrCodeElements = document.querySelectorAll(".qr-code");

    qrCodeElements.forEach(qrCodeElement => {
        const code = qrCodeElement.getAttribute("data-code");
        if (code) {
            generateQRCode(qrCodeElement, code);
        }
    });
});

function generateQRCode(qrCodeElement, code) {
    qrCodeElement.style.display = "block";
    
    const qrcode = new QRCode(qrCodeElement, {
        text: code,
        width: 180,
        height: 180,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });

    let button = document.createElement("button");
    button.className = "btn btn-primary";
    button.innerHTML = "<i class='fas fa-download'></i> Download QR Code";
    // Prepare download link
    let downloadLink = document.createElement("a");
    downloadLink.setAttribute("download", "qr_code.png");
    downloadLink.appendChild(button);
    qrCodeElement.appendChild(downloadLink);

    // Retrieve the canvas for download
    let qrCodeCanvas = qrCodeElement.querySelector("canvas");

    // Set the href for download
    if (qrCodeCanvas) {
        downloadLink.setAttribute("href", qrCodeCanvas.toDataURL());
    }
}