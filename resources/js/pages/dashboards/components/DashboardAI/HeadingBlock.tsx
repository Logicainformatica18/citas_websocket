export default function HeadingBlock({ title }) {
  return (
    <div className="col-span-full border-b border-gray-600 mb-4 mt-6 pb-2">
      <h2 className="text-xl font-bold text-blue-400">{title}</h2>
    </div>
  );
}
