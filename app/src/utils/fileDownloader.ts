export const handleDownloadFile = (
  fileName: string, 
  contentTitle: string, 
  fileType: 'pdf' | 'excel' | 'word' | 'image' | string = 'pdf', 
  customDataUrl?: string
) => {
  // If a real uploaded file Data URL exists, trigger direct download
  if (customDataUrl && customDataUrl.startsWith('data:')) {
    const link = document.createElement('a');
    link.href = customDataUrl;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    return;
  }

  // Generate dynamic browser blob download with official school header
  let mimeType = 'text/plain;charset=utf-8;';
  let formattedContent = '';

  const header = `===================================================================
                  THAMANI ACADEMY - KAKIRI
===================================================================
OFFICIAL DOCUMENT: ${contentTitle}
FILE NAME: ${fileName}
FORMAT: ${fileType.toUpperCase()}
ISSUED DATE: ${new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
UNEB CENTER NO: U0892 | KAKIRI TOWN COUNCIL, WAKISO, UGANDA
===================================================================\n\n`;

  if (fileType === 'excel') {
    mimeType = 'text/csv;charset=utf-8;';
    formattedContent = `THAMANI ACADEMY - OFFICIAL DOCUMENT DATA\nDocument Title,"${contentTitle}"\nFile Name,"${fileName}"\nIssued Date,"${new Date().toLocaleDateString()}"\nUNEB Center,"U0892"\n\nPeriod,Time,Subject,Teacher,Room Venue\nP1,08:00 AM - 08:40 AM,Mathematics,Mr. Okello,Room 4A\nP2,08:40 AM - 09:20 AM,Physics,Mr. Musoke Raymond,Lab 2\nP3,09:20 AM - 10:00 AM,Chemistry,Mrs. Nabwire Christine,Lab 1\nP4,10:30 AM - 11:10 AM,Biology,Dr. Akello,Room 4A\nP5,11:10 AM - 11:50 AM,English Literature,Mrs. Mukasa,Room 4B\nP6,01:30 PM - 02:10 PM,ICT,Mr. Kiwanuka Patrick,ICT Lab\n`;
  } else if (fileType === 'word') {
    mimeType = 'text/plain;charset=utf-8;';
    formattedContent = header + `[OFFICIAL ACADEMIC & ADMINISTRATIVE DOCUMENT]\n\n` +
      `Notice / Schedule Details:\n` +
      `-------------------------------------------------------------------\n` +
      `This official document was published by Thamani Academy Administration.\n` +
      `All scholars, teaching faculty, and staff are requested to adhere strictly to\n` +
      `the directives outlined herein.\n\n` +
      `For official verification, contact:\n` +
      `Email: info@thamani.ac.ug\n` +
      `Phone: +256 414 123 456\n` +
      `Address: Kakiri Town Council, Wakiso District, Uganda\n`;
  } else {
    // Default PDF / Document mock blob
    mimeType = 'application/pdf';
    formattedContent = header + `[OFFICIAL THAMANI ACADEMY PDF DOCUMENT]\n\n` +
      `This PDF document contains official school schedules, timetables, and administrative circulars.\n` +
      `To view full graphical formatting, please ensure your browser or PDF reader is up to date.\n\n` +
      `© ${new Date().getFullYear()} Thamani Academy. All Rights Reserved.\n`;
  }

  const blob = new Blob([formattedContent], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  setTimeout(() => URL.revokeObjectURL(url), 1500);
};
